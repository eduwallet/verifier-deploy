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
        $r->add('POST', '/api/issue-multiple', fn($req) => $this->issueMultiple($req));
        $r->add('POST', '/api/issue-presentation', fn($req) => $this->issuePresentation($req));
        $r->add('POST', '/api/session/{run}/release', fn($req, $p) => $this->release($p['run']));
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
        $catalog = $this->config->publicCatalog();
        // fields with a generated default get a value that is stable during the session
        foreach ($catalog['credentials'] as &$credential) {
            foreach ($credential['fields'] as &$field) {
                $field['default'] = $this->fieldDefault($field);
                unset($field['generate']);
            }
            unset($field);
        }
        unset($credential);
        return Response::json(200, ['csrfToken' => $this->csrfToken()] + $catalog);
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

    /** Issue the credentials of a bundle with one offer. The data is keyed by the catalog key of the credential. */
    private function issueMultiple(Request $request): Response
    {
        $body = $request->json();
        $bundle = $this->config->bundle((string) ($body['bundle'] ?? ''));
        $issuer = $this->config->issuer($bundle['issuer']);
        $input = is_array($body['data'] ?? null) ? $body['data'] : [];
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $bundle['credentials'], true)) {
                throw new ApiException(422, 'unknown_credential', 'Unknown credential');
            }
        }

        $items = [];
        $data = [];
        foreach ($bundle['credentials'] as $key) {
            $credential = $this->config->credential($key);
            $values = $this->validateClaims($credential, is_array($input[$key] ?? null) ? $input[$key] : [], true);
            $items[] = ['credential' => $credential, 'data' => $values];
            $data[$key] = $values;
        }

        $this->limiter->hit('issue', 10, 60);
        $offer = $this->issuers->createMultipleOffer($issuer, $items);

        $run = bin2hex(random_bytes(16));
        $runs = $this->session->get('runs', []);
        $runs[$run] = [
            'bundle' => $bundle['key'],
            'offerId' => $offer['id'],
            'offerUri' => $offer['uri'],
            'data' => $data,
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

    /**
     * Start an issuance during which the wallet has to present credentials first. The query comes from the catalog.
     */
    private function issuePresentation(Request $request): Response
    {
        $body = $request->json();
        $presentation = $this->config->presentation((string) ($body['presentation'] ?? ''));
        $issuer = $this->config->issuer($presentation['issuer']);

        $this->limiter->hit('issue', 10, 60);
        $offer = $this->issuers->createPresentationOffer($issuer, $presentation['credentialId'], $presentation['dcql']);

        $run = bin2hex(random_bytes(16));
        $runs = $this->session->get('runs', []);
        $runs[$run] = [
            'presentation' => $presentation['key'],
            'offerId' => $offer['id'],
            'offerUri' => $offer['uri'],
            'createdAt' => time(),
        ];
        $this->session->set('runs', array_slice($runs, -self::MAX_RUNS, null, true));
        $this->session->close();

        return $this->ok(['run' => $run, 'offerId' => $offer['id'], 'offerUri' => $offer['uri'], 'txCode' => null, 'data' => (object) []]);
    }

    /**
     * Release the deferred credential of a bundle run with the data that was offered, which is what the
     * demo back-office does: it does not change anything.
     */
    private function release(string $runId): Response
    {
        [$run, $credential, $issuer] = $this->run($runId);
        if (!isset($run['bundle'])) {
            throw new ApiException(404, 'unknown_run', 'Unknown session');
        }
        $id = $this->transactionId($run, $credential, $issuer);
        $detail = $this->issuers->deferredDetail($issuer, $id);

        // the claims the issuer accepts from the back-office, with the values that were offered
        $offered = $run['data'][$credential['key']] ?? [];
        $names = IssuerService::suppliedClaimNames($detail['claims'] ?? []);
        $claims = [];
        foreach ($names ?: array_keys($credential['fields']) as $name) {
            if (array_key_exists($name, $offered)) {
                $claims[$name] = (string) $offered[$name];
            }
        }

        $this->limiter->hit('authorize', 20, 60);
        $this->session->close();
        $result = $this->issuers->authorize($issuer, $id, $claims);
        return $this->ok(['released' => true, 'claims' => $claims, 'result' => $result]);
    }

    private function status(Request $request, string $runId): Response
    {
        [$run, , $issuer] = $this->run($runId);
        $this->session->close();
        $status = $this->withCredentialKeys($this->issuers->checkOffer($issuer, $run['offerId']), $run);
        // the poll runs every second, so the trace is only included when asked for
        return $this->ok(['run' => $runId] + $status, ($request->query['trace'] ?? '') === '1');
    }

    /**
     * The issued credentials and deferred transactions of an offer, by catalog key of the credential: the front-end
     * does not know the credential configurations of the issuer. Only the run of a bundle has them.
     * @param array<string,mixed> $status
     * @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    private function withCredentialKeys(array $status, array $run): array
    {
        $issued = [];
        $deferred = [];
        if (isset($run['bundle'])) {
            $keys = $this->config->bundle($run['bundle'])['credentials'];
            foreach ($status['issued'] as $item) {
                // the data set is '<credential configuration id>#<position in the offer>'
                if (!preg_match('/^(.+)#(\d+)$/', $item['dataset'], $match)) {
                    continue;
                }
                $key = $keys[(int) $match[2]] ?? null;
                if ($key !== null && $this->config->credential($key)['credentialId'] === $match[1]) {
                    $issued[] = ['key' => $key, 'uuid' => $item['uuid']];
                }
            }
            foreach ($status['deferred'] as $item) {
                foreach ($keys as $key) {
                    $credential = $this->config->credential($key);
                    if (($credential['deferred'] ?? false) && $credential['credentialId'] === $item['credential']) {
                        $deferred[] = ['id' => $item['id'], 'key' => $key] + array_diff_key($item, ['id' => 1, 'credential' => 1]);
                        break;
                    }
                }
            }
        }
        return ['issued' => $issued, 'deferred' => $deferred] + $status;
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
        if (isset($run['bundle'])) {
            // the run of a bundle: its deferred credential is the one the back-office works on
            $bundle = $this->config->bundle($run['bundle']);
            foreach ($bundle['credentials'] as $key) {
                $credential = $this->config->credential($key);
                if ($credential['deferred'] ?? false) {
                    return [$run, $credential, $this->config->issuer($bundle['issuer'])];
                }
            }
            throw new ApiException(500, 'configuration_error', 'The bundle has no deferred credential');
        }
        if (isset($run['presentation'])) {
            // the credential that is issued after the presentation: it has no fields, the issuer fills it
            $presentation = $this->config->presentation($run['presentation']);
            $credential = ['key' => $presentation['key'], 'credentialId' => $presentation['credentialId'], 'fields' => []];
            return [$run, $credential, $this->config->issuer($presentation['issuer'])];
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
                $claims[$name] = $this->fieldDefault($field);
            }
        }
        return $claims;
    }

    /**
     * The default of a catalog field. A field with 'generate' (a prefix) has a random value, the same one during the session.
     * @param array<string,mixed> $field
     */
    private function fieldDefault(array $field): string
    {
        if (!isset($field['generate'])) {
            return (string) $field['default'];
        }
        $generated = $this->session->get('generated', []);
        $generated = is_array($generated) ? $generated : [];
        $prefix = (string) $field['generate'];
        if (!isset($generated[$prefix])) {
            $generated[$prefix] = $prefix . bin2hex(random_bytes(6));
            $this->session->set('generated', $generated);
        }
        return $generated[$prefix];
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
