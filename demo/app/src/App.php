<?php

namespace Demo;

use Demo\Http\Request;
use Demo\Http\Response;
use Demo\Http\Router;
use Demo\Http\Transport;
use Demo\Service\IssuerService;
use Demo\Service\RateLimiter;
use Demo\Service\Trace;
use Demo\Service\UpstreamClient;
use Demo\Session\SessionInterface;

/**
 * The front-end facing API. The front-end sends catalog keys and run identifiers only; this class
 * resolves them to issuers, tokens and offers that were created in the visitor's own session.
 */
final class App
{
    private const MAX_RUNS = 20;

    private Router $router;
    private Trace $trace;
    private IssuerService $issuers;
    private RateLimiter $limiter;

    public function __construct(private Config $config, private SessionInterface $session, Transport $transport)
    {
        $this->trace = new Trace();
        $this->issuers = new IssuerService(new UpstreamClient($transport, $this->trace));
        $this->limiter = new RateLimiter($session);
        $this->router = new Router();
        $this->routes();
    }

    private function routes(): void
    {
        $r = $this->router;
        $r->add('GET', '/api/catalog', fn($req) => $this->catalog());
        $r->add('POST', '/api/issue', fn($req) => $this->issue($req));
        $r->add('GET', '/api/session/{run}', fn($req, $p) => $this->status($req, $p['run']));
        $r->add('GET', '/api/session/{run}/offer', fn($req, $p) => $this->offer($p['run']));
        $r->add('GET', '/api/session/{run}/deferred', fn($req, $p) => $this->deferred($p['run']));
        $r->add('POST', '/api/session/{run}/deferred/authorize', fn($req, $p) => $this->authorize($req, $p['run']));
        $r->add('POST', '/api/session/{run}/deferred/deny', fn($req, $p) => $this->deny($req, $p['run']));
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->method !== 'GET') {
                $this->checkCsrf($request);
            }
            $this->limiter->hit('all', 300, 60);
            return $this->router->dispatch($request);
        } catch (ApiException $e) {
            return $this->error($e);
        } catch (\Throwable $e) {
            error_log('unhandled ' . get_class($e) . ': ' . $e->getMessage());
            return $this->error(new ApiException(500, 'internal_error', 'Internal server error'));
        }
    }

    // ---- endpoints ----------------------------------------------------------------------------

    private function catalog(): Response
    {
        return Response::json(200, ['csrfToken' => $this->csrfToken()] + $this->config->publicCatalog());
    }

    private function issue(Request $request): Response
    {
        $body = $request->json();
        $credential = $this->config->credential((string) ($body['credential'] ?? ''));
        $issuer = $this->config->issuer($credential['issuer']);
        $data = $this->validateClaims($credential, is_array($body['data'] ?? null) ? $body['data'] : [], true);

        $this->limiter->hit('issue', 10, 60);
        $offer = $this->issuers->createOffer($issuer, $credential, $data);

        $run = bin2hex(random_bytes(16));
        $runs = $this->session->get('runs', []);
        $runs[$run] = [
            'credential' => $credential['key'],
            'offerId' => $offer['id'],
            'offerUri' => $offer['uri'],
            'createdAt' => time(),
        ];
        $this->session->set('runs', array_slice($runs, -self::MAX_RUNS, null, true));
        $this->session->close();

        return $this->ok([
            'run' => $run,
            'offerId' => $offer['id'],
            'offerUri' => $offer['uri'],
            'txCode' => $offer['txCode'],
            'data' => $data,
        ]);
    }

    private function status(Request $request, string $runId): Response
    {
        [$run, , $issuer] = $this->run($runId);
        $this->session->close();
        $status = $this->issuers->checkOffer($issuer, $run['offerId']);
        // the poll runs every second, so the trace is only included when asked for
        return $this->ok(['run' => $runId] + $status, ($request->query['trace'] ?? '') === '1');
    }

    private function offer(string $run): Response
    {
        [$run, , $issuer] = $this->run($run);
        $this->session->close();
        return $this->ok([
            'offerUri' => $run['offerUri'],
            'offer' => $this->issuers->fetchOffer($issuer, $run['offerUri']),
        ]);
    }

    private function deferred(string $runId): Response
    {
        [$run, $credential, $issuer] = $this->run($runId);
        $this->session->close();
        $ownId = $this->issuers->findTransactionId($issuer, $run['offerId'], $credential['credentialId']);
        $list = $this->issuers->listDeferred($issuer, $credential['credentialId'], $ownId);
        return $this->ok($list);
    }

    private function authorize(Request $request, string $runId): Response
    {
        [$run, $credential, $issuer] = $this->run($runId);
        $body = $request->json();
        $claims = $this->validateClaims($credential, is_array($body['claims'] ?? null) ? $body['claims'] : [], false);
        $this->limiter->hit('authorize', 20, 60);
        $this->session->close();
        $result = $this->issuers->authorize($issuer, $this->transactionId($run, $credential, $issuer), $claims);
        return $this->ok(['authorized' => true, 'claims' => $claims, 'result' => $result]);
    }

    private function deny(Request $request, string $runId): Response
    {
        [$run, $credential, $issuer] = $this->run($runId);
        $reason = $request->json()['reason'] ?? null;
        if ($reason !== null && (!is_string($reason) || strlen($reason) > 500)) {
            throw new ApiException(422, 'invalid_reason', 'The reason must be a string of at most 500 characters');
        }
        $this->limiter->hit('authorize', 20, 60);
        $this->session->close();
        $result = $this->issuers->deny($issuer, $this->transactionId($run, $credential, $issuer), $reason);
        return $this->ok(['denied' => true, 'result' => $result]);
    }

    // ---- helpers ------------------------------------------------------------------------------

    /**
     * The run of this visitor session, with its credential and issuer. Runs of other sessions do not exist here.
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function run(string $id): array
    {
        $run = $this->session->get('runs', [])[$id] ?? null;
        if (!is_array($run) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new ApiException(404, 'unknown_run', 'Unknown session');
        }
        $credential = $this->config->credential($run['credential']);
        return [$run, $credential, $this->config->issuer($credential['issuer'])];
    }

    /**
     * The id of the deferred transaction of this run: the wallet has to have requested the credential first.
     * @param array<string,mixed> $run
     * @param array<string,mixed> $credential
     * @param array<string,mixed> $issuer
     */
    private function transactionId(array $run, array $credential, array $issuer): string
    {
        $id = $this->issuers->findTransactionId($issuer, $run['offerId'], $credential['credentialId']);
        if ($id === null) {
            throw new ApiException(404, 'not_found', 'The deferred transaction does not exist (yet)');
        }
        return $id;
    }

    /**
     * Only the claims defined in the catalog are accepted, as strings of a limited length.
     * @param array<string,mixed> $credential
     * @param array<string,mixed> $input
     * @return array<string,string>
     */
    private function validateClaims(array $credential, array $input, bool $useDefaults): array
    {
        $claims = [];
        foreach ($input as $name => $value) {
            if (!isset($credential['fields'][$name])) {
                throw new ApiException(422, 'unknown_claim', "Unknown claim '$name'");
            }
        }
        foreach ($credential['fields'] as $name => $field) {
            if (array_key_exists($name, $input)) {
                $value = $input[$name];
                if (!is_string($value) || mb_strlen($value) > $field['maxLength']) {
                    throw new ApiException(422, 'invalid_claim', "Claim '$name' must be a text of at most {$field['maxLength']} characters");
                }
                $claims[$name] = trim($value);
            } elseif ($useDefaults) {
                $claims[$name] = $field['default'];
            }
        }
        return $claims;
    }

    private function csrfToken(): string
    {
        $token = $this->session->get('csrf');
        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('csrf', $token);
        }
        return $token;
    }

    private function checkCsrf(Request $request): void
    {
        $expected = $this->session->get('csrf');
        $given = $request->header('X-CSRF-Token');
        if (!is_string($expected) || !is_string($given) || !hash_equals($expected, $given)) {
            throw new ApiException(403, 'csrf', 'Invalid or missing CSRF token');
        }
    }

    /** @param array<string,mixed> $data */
    private function ok(array $data, bool $withTrace = true): Response
    {
        if ($withTrace && $this->config->bool('DEMO_TRACE', true)) {
            $data['trace'] = $this->trace->entries();
        }
        return Response::json(200, $data);
    }

    private function error(ApiException $e): Response
    {
        $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
        if ($e->detail !== null) {
            $error['detail'] = $e->detail;
        }
        $body = ['error' => $error];
        if ($this->config->bool('DEMO_TRACE', true) && $this->trace->entries() !== []) {
            $body['trace'] = $this->trace->entries();
        }
        return Response::json($e->status, $body);
    }
}
