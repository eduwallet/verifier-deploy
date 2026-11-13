<?php

namespace Demo\Tests;

use Demo\App;
use Demo\Config;
use Demo\Http\Request;
use Demo\Http\Response;
use Demo\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    private const BASE = 'https://issuer.test';
    private const TOKEN = 'super-secret-token';
    private const OWN = '11111111-1111-1111-1111-111111111111';
    private const OTHER = '22222222-2222-2222-2222-222222222222';
    // a transaction is identified by '<offer id>.<transaction id>'
    private const OWN_TX = self::OWN . '.tx-own';
    private const OTHER_TX = self::OTHER . '.tx-other';

    private FakeTransport $issuer;
    private ArraySession $session;
    private App $app;

    protected function setUp(): void
    {
        $this->issuer = new FakeTransport();
        $this->session = new ArraySession(['csrf' => 'csrf-token']);
        $this->app = $this->makeApp($this->session);
    }

    private function makeApp(ArraySession $session, string $token = self::TOKEN): App
    {
        $catalog = (require __DIR__ . '/../private/catalog.php')([
            'ISSUER_SANDBOX_URL' => self::BASE,
            'ISSUER_DEV_SANDBOX' => $token,
        ]);
        return new App(new Config([], $catalog), $session, $this->issuer);
    }

    /** @return array<string,mixed> */
    private function call(string $method, string $path, ?array $body = null, bool $csrf = true, array $query = [], ?App $app = null): array
    {
        $headers = $csrf ? ['x-csrf-token' => 'csrf-token'] : [];
        $response = ($app ?? $this->app)->handle(new Request($method, $path, $query, $headers, $body === null ? '' : json_encode($body)));
        $this->assertInstanceOf(Response::class, $response);
        return ['status' => $response->status, 'json' => json_decode($response->body, true), 'raw' => $response->body];
    }

    /** create an offer and return the run identifier */
    private function issue(?App $app = null, ?ArraySession $session = null): string
    {
        $this->issuer->on('POST', self::BASE . '/api/create-offer', ['uri' => 'openid-credential-offer://?credential_offer_uri=' . rawurlencode(self::BASE . '/get-credential-offer/' . self::OWN), 'id' => self::OWN]);
        $result = $this->call('POST', '/api/issue', ['credential' => 'deferred', 'data' => []], app: $app);
        $this->assertSame(200, $result['status'], $result['raw']);
        return $result['json']['run'];
    }

    public function testStateChangingCallsNeedTheCsrfToken(): void
    {
        $result = $this->call('POST', '/api/issue', ['credential' => 'deferred'], csrf: false);
        $this->assertSame(403, $result['status']);
        $this->assertSame([], $this->issuer->calls);
    }

    public function testCatalogGivesCsrfTokenAndNoSecrets(): void
    {
        $result = $this->call('GET', '/api/catalog');
        $this->assertSame(200, $result['status']);
        $this->assertSame('csrf-token', $result['json']['csrfToken']);
        $this->assertSame('Demo User', $result['json']['credentials']['deferred']['fields'][0]['default']);
        $this->assertStringNotContainsString(self::TOKEN, $result['raw']);
        $this->assertStringNotContainsString(self::BASE, $result['raw']);
        $this->assertStringNotContainsString('DeferredCredential', $result['raw']);
    }

    public function testUnknownRoutes(): void
    {
        $this->assertSame(404, $this->call('GET', '/api/nope')['status']);
        $this->assertSame(405, $this->call('GET', '/api/issue')['status']);
    }

    public function testIssueCreatesAnOfferWithDefaultsAndRedactsTheToken(): void
    {
        $run = $this->issue();
        $call = $this->issuer->calls[0];
        $this->assertContains('Authorization: Bearer ' . self::TOKEN, $call['headers']);
        $body = json_decode($call['body'], true);
        $this->assertSame(['DeferredCredential'], $body['credentials']);
        $this->assertSame(['name' => 'Demo User', 'email' => 'demouser@example.com', 'institute' => 'Example Inc'], $body['credentialDataSupplierInput']);
        $this->assertArrayHasKey('urn:ietf:params:oauth:grant-type:pre-authorized_code', $body['grants']);

        $result = $this->call('GET', "/api/session/$run", query: ['trace' => '1']);
        $this->assertStringNotContainsString(self::TOKEN, $result['raw']);
        $this->assertSame('Bearer ***', $result['json']['trace'][0]['requestHeaders']['Authorization'] ?? null);
    }

    public function testIssueUsesTheSuppliedValuesAndRejectsOthers(): void
    {
        $this->issuer->on('POST', self::BASE . '/api/create-offer', ['uri' => 'x', 'id' => self::OWN]);
        $ok = $this->call('POST', '/api/issue', ['credential' => 'deferred', 'data' => ['name' => 'Alice']]);
        $this->assertSame(200, $ok['status']);
        $this->assertSame('Alice', $ok['json']['data']['name']);
        $this->assertSame('Example Inc', $ok['json']['data']['institute']);

        $this->assertSame(422, $this->call('POST', '/api/issue', ['credential' => 'deferred', 'data' => ['role' => 'admin']])['status']);
        $this->assertSame(422, $this->call('POST', '/api/issue', ['credential' => 'deferred', 'data' => ['name' => str_repeat('x', 101)]])['status']);
        $this->assertSame(422, $this->call('POST', '/api/issue', ['credential' => 'deferred', 'data' => ['name' => ['a']]])['status']);
        $this->assertSame(422, $this->call('POST', '/api/issue', ['credential' => 'other'])['status']);
    }

    public function testIssueNeedsAConfiguredIssuer(): void
    {
        $app = $this->makeApp($this->session, '');
        $result = $this->call('POST', '/api/issue', ['credential' => 'deferred'], app: $app);
        $this->assertSame(503, $result['status']);
        $this->assertSame([], $this->issuer->calls);
    }

    public function testIssueIsRateLimited(): void
    {
        $this->issuer->on('POST', self::BASE . '/api/create-offer', ['uri' => 'x', 'id' => self::OWN]);
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(200, $this->call('POST', '/api/issue', ['credential' => 'deferred'])['status']);
        }
        $this->assertSame(429, $this->call('POST', '/api/issue', ['credential' => 'deferred'])['status']);
    }

    public function testStatusMapsPhaseAndOrdersTheRequests(): void
    {
        $run = $this->issue();
        $this->issuer->on('POST', self::BASE . '/api/check-offer', [
            'status' => 'CREDENTIAL_PENDING',
            'createdAt' => 1, 'lastUpdatedAt' => 2,
            'expiresAt' => (time() + 600) * 1000,
            'requests' => [
                'notification-request' => ['event' => 'credential_accepted'],
                'get_credential-response' => ['transaction_id' => 't'],
                'create_offer-request' => [],
                'custom' => 1,
            ],
        ]);
        $result = $this->call('GET', "/api/session/$run");
        $this->assertSame('pending', $result['json']['phase']);
        $this->assertSame('CREDENTIAL_PENDING', $result['json']['status']);
        $this->assertSame(['create_offer-request', 'get_credential-response', 'notification-request', 'custom'], array_keys($result['json']['requests']));
        $this->assertSame(['event' => 'credential_accepted'], $result['json']['notification']);
        $this->assertArrayNotHasKey('trace', $result['json'], 'polling responses carry no trace unless asked for');
        $this->assertSame(self::OWN, json_decode($this->issuer->calls[1]['body'], true)['id']);
    }

    public function testStatusReportsExpiry(): void
    {
        $run = $this->issue();
        $this->issuer->on('POST', self::BASE . '/api/check-offer', ['status' => 'CREDENTIAL_PENDING', 'expiresAt' => (time() - 5) * 1000]);
        $this->assertSame('expired', $this->call('GET', "/api/session/$run")['json']['phase']);
        $this->issuer->on('POST', self::BASE . '/api/check-offer', ['status' => 'CREDENTIAL_ISSUED', 'expiresAt' => (time() - 5) * 1000]);
        $this->assertSame('issued', $this->call('GET', "/api/session/$run")['json']['phase']);
    }

    public function testRunsOfOtherSessionsAreUnknown(): void
    {
        $run = $this->issue();
        $other = new ArraySession(['csrf' => 'csrf-token']);
        $app = $this->makeApp($other);
        foreach (['/api/session/' . $run, "/api/session/$run/offer", "/api/session/$run/deferred"] as $path) {
            $this->assertSame(404, $this->call('GET', $path, app: $app)['status'], $path);
        }
        $this->assertSame(404, $this->call('POST', "/api/session/$run/deferred/authorize", [], app: $app)['status']);
        $this->assertSame(404, $this->call('GET', '/api/session/not-a-run')['status']);
    }

    public function testOfferIsFetchedFromTheIssuerOnly(): void
    {
        $run = $this->issue();
        $offer = ['credential_issuer' => self::BASE, 'credential_configuration_ids' => ['DeferredCredential']];
        $this->issuer->on('GET', self::BASE . '/get-credential-offer/' . self::OWN, $offer);
        $result = $this->call('GET', "/api/session/$run/offer");
        $this->assertSame(200, $result['status']);
        $this->assertSame($offer, $result['json']['offer']);
        $last = end($this->issuer->calls);
        $this->assertSame([], array_filter($last['headers'], fn($h) => str_starts_with($h, 'Authorization')), 'no token to the offer endpoint');
    }

    public function testOfferFromAnotherHostIsRefused(): void
    {
        $this->issuer->on('POST', self::BASE . '/api/create-offer', ['uri' => 'openid-credential-offer://?credential_offer_uri=' . rawurlencode('https://evil.test/offer'), 'id' => self::OWN]);
        $run = $this->call('POST', '/api/issue', ['credential' => 'deferred'])['json']['run'];
        $count = count($this->issuer->calls);
        $this->assertSame(502, $this->call('GET', "/api/session/$run/offer")['status']);
        $this->assertCount($count, $this->issuer->calls, 'the foreign host is never contacted');
    }

    public function testInlineOfferIsParsed(): void
    {
        $this->issuer->on('POST', self::BASE . '/api/create-offer', ['uri' => 'openid-credential-offer://?credential_offer=' . rawurlencode('{"a":1}'), 'id' => self::OWN]);
        $run = $this->call('POST', '/api/issue', ['credential' => 'deferred'])['json']['run'];
        $this->assertSame(['a' => 1], $this->call('GET', "/api/session/$run/offer")['json']['offer']);
    }

    public function testDeferredListFlagsOwnAndHidesOtherValues(): void
    {
        $run = $this->issue();
        $in10min = (time() + 600) * 1000;
        $this->scriptOwnTransaction();
        $this->issuer->on('GET', self::BASE . '/api/v2/deferred', [
            ['id' => self::OTHER_TX, 'credential_configuration_id' => 'DeferredCredential', 'status' => 'CREDENTIAL_PENDING', 'createdAt' => 1, 'expiresAt' => $in10min],
            ['id' => self::OWN_TX, 'credential_configuration_id' => 'DeferredCredential', 'status' => 'CREDENTIAL_PENDING', 'createdAt' => 2, 'expiresAt' => $in10min],
            ['id' => '33333333-3333-3333-3333-333333333333', 'credential_configuration_id' => 'PID', 'status' => 'CREDENTIAL_PENDING', 'createdAt' => 3, 'expiresAt' => $in10min],
        ]);
        $detail = fn(string $id, array $data) => ['id' => $id, 'status' => 'CREDENTIAL_PENDING', 'createdAt' => 1, 'expiresAt' => $in10min,
            'credential_configuration_id' => 'DeferredCredential', 'proofs' => 1, 'ttl' => 3600, 'interval' => 5, 'data' => $data, 'claims_supplied' => [],
            'claims' => [['path' => ['name'], 'type' => 'string', 'mandatory' => true, 'origin' => [['#any', 'name']], 'supply' => [['name']]]]];
        $this->issuer->on('GET', self::BASE . '/api/v2/deferred/' . self::OTHER_TX, $detail(self::OTHER_TX, ['name' => 'Secret Person']));
        $this->issuer->on('GET', self::BASE . '/api/v2/deferred/' . self::OWN_TX, $detail(self::OWN_TX, ['name' => 'Demo User', 'email' => 'demouser@example.com']));

        $result = $this->call('GET', "/api/session/$run/deferred");
        $this->assertSame(200, $result['status'], $result['raw']);
        $items = $result['json']['items'];
        $this->assertCount(2, $items, 'other credential types are not listed');
        $this->assertSame([self::OWN_TX, self::OTHER_TX], array_column($items, 'id'));
        $this->assertSame([true, false], array_column($items, 'own'));
        $this->assertSame(['name', 'email'], $items[0]['claimsKnown']);
        $this->assertSame('11111111', $items[0]['shortId']);
        $this->assertGreaterThan(500, $items[0]['expiresInSeconds']);
        $this->assertStringNotContainsString('Secret Person', json_encode($items));
        $this->assertSame(self::OWN_TX, $result['json']['own']['id']);
        $this->assertSame('Demo User', $result['json']['own']['data']['name']);
        $this->assertSame('string', $result['json']['own']['claims'][0]['type']);
    }

    public function testEmptyJsonObjectsOfTheIssuerStayObjects(): void
    {
        $run = $this->issue();
        $this->issuer->on('POST', self::BASE . '/api/check-offer', [
            'status' => 'CREDENTIAL_ISSUED',
            'requests' => ['notification-request' => new \stdClass(), 'get_credential-request' => new \stdClass()],
        ]);
        $raw = $this->call('GET', "/api/session/$run")['raw'];
        $this->assertStringContainsString('"get_credential-request":{}', $raw);
        $this->assertStringContainsString('"notification":{}', $raw);

        $in = (time() + 600) * 1000;
        $this->scriptOwnTransaction();
        $this->issuer->on('GET', self::BASE . '/api/v2/deferred', []);
        $this->issuer->on('GET', self::BASE . '/api/v2/deferred/' . self::OWN_TX, [
            'id' => self::OWN_TX, 'status' => 'CREDENTIAL_PENDING', 'createdAt' => 1, 'expiresAt' => $in, 'credential_configuration_id' => 'DeferredCredential',
            'proofs' => 1, 'ttl' => 1, 'interval' => 1, 'data' => new \stdClass(), 'claims_supplied' => new \stdClass(), 'claims' => [],
        ]);
        $raw = $this->call('GET', "/api/session/$run/deferred", query: ['trace' => '1'])['raw'];
        $this->assertStringContainsString('"data":{}', $raw);
        $this->assertStringContainsString('"claims_supplied":{}', $raw);
        $this->assertStringContainsString('"responseBody":{"id":', $raw, 'the trace shows what the issuer sent');
    }

    public function testAuthorizeSendsOnlyKnownClaimsForTheOwnTransaction(): void
    {
        $run = $this->issue();
        $this->scriptOwnTransaction();
        $this->issuer->on('POST', self::BASE . '/api/v2/deferred/' . self::OWN_TX, ['status' => 'CREDENTIAL_READY']);
        $result = $this->call('POST', "/api/session/$run/deferred/authorize", ['claims' => ['name' => 'Bob', 'email' => 'bob@example.com']]);
        $this->assertSame(200, $result['status'], $result['raw']);
        $call = end($this->issuer->calls);
        $this->assertSame('/api/v2/deferred/' . self::OWN_TX, substr($call['url'], strlen(self::BASE)));
        $this->assertSame(['claims' => ['name' => 'Bob', 'email' => 'bob@example.com']], json_decode($call['body'], true));

        $this->assertSame(422, $this->call('POST', "/api/session/$run/deferred/authorize", ['claims' => ['admin' => 'yes']])['status']);
    }

    public function testAuthorizeMapsIssuerErrors(): void
    {
        $run = $this->issue();
        $this->scriptOwnTransaction();
        $url = self::BASE . '/api/v2/deferred/' . self::OWN_TX;
        $this->issuer->on('POST', $url, ['errors' => ['missing name']], 400);
        $result = $this->call('POST', "/api/session/$run/deferred/authorize", ['claims' => []]);
        $this->assertSame(422, $result['status']);
        $this->assertSame('claims_rejected', $result['json']['error']['code']);
        $this->assertSame(['missing name'], $result['json']['error']['detail']['body']['errors']);

        $this->issuer->on('POST', $url, ['error' => 'closed'], 409);
        $this->assertSame(409, $this->call('POST', "/api/session/$run/deferred/authorize", ['claims' => []])['status']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($result));
    }

    public function testDenySendsTheStatusAndReason(): void
    {
        $run = $this->issue();
        $this->scriptOwnTransaction();
        $this->issuer->on('POST', self::BASE . '/api/v2/deferred/' . self::OWN_TX, ['status' => 'CREDENTIAL_DENIED']);
        $this->assertSame(200, $this->call('POST', "/api/session/$run/deferred/deny", ['reason' => 'demo'])['status']);
        $this->assertSame(['status' => 'CREDENTIAL_DENIED', 'reason' => 'demo'], json_decode(end($this->issuer->calls)['body'], true));
        $this->assertSame(422, $this->call('POST', "/api/session/$run/deferred/deny", ['reason' => ['x']])['status']);
    }

    public function testAuthorizeBeforeTheWalletRequestedTheCredentialIsNotFound(): void
    {
        $run = $this->issue();
        // no transaction exists until the wallet requests the credential
        $this->issuer->on('POST', self::BASE . '/api/check-offer', ['status' => 'CREDENTIAL_OFFERED']);
        $result = $this->call('POST', "/api/session/$run/deferred/authorize", ['claims' => []]);
        $this->assertSame(404, $result['status']);
        $this->assertSame('not_found', $result['json']['error']['code']);
    }

    public function testUpstreamFailureIsReportedGenerically(): void
    {
        $run = $this->issue();
        $result = $this->call('GET', "/api/session/$run"); // no check-offer response scripted
        $this->assertSame(502, $result['status']);
        $this->assertSame('upstream_unreachable', $result['json']['error']['code']);
        $this->assertStringNotContainsString(self::TOKEN, $result['raw']);
    }

    /** The check-offer response lists the transaction of this offer once the wallet requested the credential. */
    private function scriptOwnTransaction(): void
    {
        $this->issuer->on('POST', self::BASE . '/api/check-offer', [
            'status' => 'CREDENTIAL_PENDING',
            'deferred' => [['id' => self::OWN_TX, 'credential_configuration_id' => 'DeferredCredential', 'status' => 'CREDENTIAL_PENDING', 'expiresAt' => (time() + 600) * 1000]],
        ]);
    }

    // ---- multiple credentials in one offer ---------------------------------------------------------

    /** create an offer for the bundle and return the run identifier */
    private function issueMultiple(array $data = []): string
    {
        $this->issuer->on('POST', self::BASE . '/api/v2/create-offer', ['uri' => 'openid-credential-offer://?credential_offer_uri=' . rawurlencode(self::BASE . '/get-credential-offer/' . self::OWN), 'id' => self::OWN]);
        $result = $this->call('POST', '/api/issue-multiple', ['bundle' => 'multiple', 'data' => $data]);
        $this->assertSame(200, $result['status'], $result['raw']);
        return $result['json']['run'];
    }

    public function testCatalogListsTheBundle(): void
    {
        $bundles = $this->call('GET', '/api/catalog')['json']['bundles'];
        $this->assertSame(['generic1', 'generic2', 'deferred'], $bundles['multiple']['credentials']);
    }

    public function testMultipleOfferHasOneEntryPerCredentialWithDefaults(): void
    {
        $this->issueMultiple(['generic2' => ['grade' => 'A']]);
        $call = null;
        foreach ($this->issuer->calls as $c) {
            if (str_ends_with($c['url'], '/api/v2/create-offer')) {
                $call = $c;
            }
        }
        $body = json_decode($call['body'], true);
        $this->assertSame('pre-authorized_code', $body['grant']);
        $this->assertSame(['GenericCredential_JWT', 'GenericCredential_JWT', 'DeferredCredential'], array_column($body['credentials'], 'credential_configuration_id'));
        $first = $body['credentials'][0]['claims'];
        $this->assertSame(['credentialSubject', 'institute'], $first[0]['path']);
        $this->assertSame('Education Inc', $first[0]['value']);
        $this->assertSame(['Lessons Learned', 'B-'], [$first[1]['value'], $first[2]['value']]);
        $this->assertSame(['Digital Knowledge', 'A'], [$body['credentials'][1]['claims'][1]['value'], $body['credentials'][1]['claims'][2]['value']]);
        $this->assertSame(['name', 'email', 'institute'], array_map(fn($c) => $c['path'][1], $body['credentials'][2]['claims']));
    }

    public function testMultipleOfferValidatesTheInput(): void
    {
        $this->assertSame(422, $this->call('POST', '/api/issue-multiple', ['bundle' => 'nope'])['status']);
        $this->assertSame(422, $this->call('POST', '/api/issue-multiple', ['bundle' => 'multiple', 'data' => ['PID' => []]])['status']);
        $this->assertSame(422, $this->call('POST', '/api/issue-multiple', ['bundle' => 'multiple', 'data' => ['generic1' => ['admin' => 'x']]])['status']);
        $this->assertSame(422, $this->call('POST', '/api/issue-multiple', ['bundle' => 'multiple', 'data' => ['generic1' => ['grade' => ['a']]]])['status']);
        $this->assertSame([], $this->issuer->calls);
    }

    public function testStatusOfMultipleOfferListsIssuedAndDeferred(): void
    {
        $run = $this->issueMultiple();
        $this->issuer->on('POST', self::BASE . '/api/check-offer', [
            'status' => 'CREDENTIAL_PENDING',
            'credentials' => [
                ['uuid' => 'u1', 'dataset_id' => 'GenericCredential_JWT#0', 'credential_configuration_id' => 'GenericCredential_JWT'],
                ['uuid' => 'u2', 'dataset_id' => 'GenericCredential_JWT#1', 'credential_configuration_id' => 'GenericCredential_JWT'],
                ['uuid' => 'bad', 'dataset_id' => 'GenericCredential_JWT#2', 'credential_configuration_id' => 'GenericCredential_JWT'],
            ],
            'deferred' => [['id' => self::OWN_TX, 'credential_configuration_id' => 'DeferredCredential', 'status' => 'CREDENTIAL_PENDING', 'expiresAt' => 5]],
        ]);
        $json = $this->call('GET', "/api/session/$run")['json'];
        $this->assertSame([['key' => 'generic1', 'uuid' => 'u1'], ['key' => 'generic2', 'uuid' => 'u2']], $json['issued'], 'by catalog key, a data set that does not fit the offer is ignored');
        $this->assertSame([['id' => self::OWN_TX, 'key' => 'deferred', 'status' => 'CREDENTIAL_PENDING', 'expiresAt' => 5, 'reason' => null]], $json['deferred']);
        $this->assertStringNotContainsString('Credential', json_encode([$json['issued'], $json['deferred']]), 'no issuer configuration ids');

        $single = $this->issue();
        $this->assertSame([], $this->call('GET', "/api/session/$single")['json']['issued']);
    }

    public function testReleaseSendsTheOfferedDataOfTheDeferredCredential(): void
    {
        $run = $this->issueMultiple(['deferred' => ['name' => 'Alice']]);
        $this->scriptOwnTransaction();
        $this->issuer->on('GET', self::BASE . '/api/v2/deferred/' . self::OWN_TX, [
            'id' => self::OWN_TX, 'status' => 'CREDENTIAL_PENDING', 'credential_configuration_id' => 'DeferredCredential',
            'data' => ['name' => 'Alice'], 'claims_supplied' => [],
            'claims' => [['path' => ['name'], 'type' => 'string', 'mandatory' => true, 'origin' => [], 'supply' => [['name']]],
                         ['path' => ['email'], 'type' => 'string', 'mandatory' => false, 'origin' => [], 'supply' => [['email']]]],
        ]);
        $this->issuer->on('POST', self::BASE . '/api/v2/deferred/' . self::OWN_TX, ['status' => 'CREDENTIAL_READY']);
        $result = $this->call('POST', "/api/session/$run/release", []);
        $this->assertSame(200, $result['status'], $result['raw']);
        $call = end($this->issuer->calls);
        $this->assertSame('/api/v2/deferred/' . self::OWN_TX, substr($call['url'], strlen(self::BASE)));
        $this->assertSame(['claims' => ['name' => 'Alice', 'email' => 'demouser@example.com']], json_decode($call['body'], true));
    }

    public function testReleaseNeedsABundleRunAndATransaction(): void
    {
        $single = $this->issue();
        $this->assertSame(404, $this->call('POST', "/api/session/$single/release", [])['status']);

        $run = $this->issueMultiple();
        $this->issuer->on('POST', self::BASE . '/api/check-offer', ['status' => 'ACCESS_TOKEN_CREATED']);
        $this->assertSame(404, $this->call('POST', "/api/session/$run/release", [])['status']);
    }
}
