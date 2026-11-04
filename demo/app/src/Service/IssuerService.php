<?php

namespace Demo\Service;

use Demo\ApiException;

/** The calls to the issuer API (create-offer, check-offer and the v2 deferred back-office API). */
final class IssuerService
{
    /** The order in which the issuer stores the request/response phases of a session */
    private const PHASE_ORDER = [
        'create_offer-request', 'create_offer-response',
        'credential_offer-request', 'credential_offer-response',
        'access_token-request', 'access_token-response',
        'get_credential-request', 'get_credential-response',
        'deferred-request', 'deferred-response',
        'deferred-denied',
        'notification-request',
    ];

    private const PHASES = [
        'OFFER_CREATED' => 'created',
        'OFFER_URI_RETRIEVED' => 'retrieved',
        'ACCESS_TOKEN_REQUESTED' => 'token',
        'ACCESS_TOKEN_CREATED' => 'token',
        'CREDENTIAL_REQUEST_RECEIVED' => 'processing',
        'CREDENTIAL_PENDING' => 'pending',
        'CREDENTIAL_READY' => 'ready',
        'CREDENTIAL_DENIED' => 'denied',
        'CREDENTIAL_ISSUED' => 'issued',
    ];

    /** the maximum number of transaction details fetched for the list of open transactions */
    private const MAX_DETAILS = 25;

    public function __construct(private UpstreamClient $client)
    {
    }

    /**
     * @param array{baseUrl:string,token:string} $issuer
     * @param array<string,mixed> $credential catalog entry
     * @param array<string,string> $data the claims for the offer
     * @return array{uri:string,id:string,txCode:?string}
     */
    public function createOffer(array $issuer, array $credential, array $data): array
    {
        $body = [
            'credentials' => [$credential['credentialId']],
            'grants' => ['urn:ietf:params:oauth:grant-type:pre-authorized_code' => ['pre-authorized_code' => 'generate']],
            'credentialDataSupplierInput' => $data,
        ];
        $response = $this->call($issuer, 'create-offer', 'POST', '/api/create-offer', $body);
        $this->expect($response, [200], 'create the offer');
        $json = $response->json;
        if (!is_array($json) || !is_string($json['uri'] ?? null) || !is_string($json['id'] ?? null)) {
            throw new ApiException(502, 'upstream_error', 'The issuer returned an unexpected offer');
        }
        return ['uri' => $json['uri'], 'id' => $json['id'], 'txCode' => is_string($json['txCode'] ?? null) ? $json['txCode'] : null];
    }

    /**
     * The status of the offer, with the request/response data in protocol order.
     * @param array{baseUrl:string,token:string} $issuer
     * @return array<string,mixed>
     */
    public function checkOffer(array $issuer, string $id): array
    {
        $response = $this->call($issuer, 'check-offer', 'POST', '/api/check-offer', ['id' => $id]);
        $this->expect($response, [200], 'read the offer status');
        $json = $response->json;
        if (!is_array($json) || !is_string($json['status'] ?? null)) {
            throw new ApiException(502, 'upstream_error', 'The issuer returned an unexpected status');
        }
        $status = $json['status'];
        $expiresAt = isset($json['expiresAt']) && is_numeric($json['expiresAt']) ? (int) $json['expiresAt'] : null;
        $phase = self::PHASES[$status] ?? 'error';
        if ($expiresAt !== null && $phase !== 'issued' && $expiresAt < (int) (microtime(true) * 1000)) {
            $phase = 'expired';
        }
        $raw = $response->object;
        $requests = is_object($raw->requests ?? null) ? self::orderRequests((array) $raw->requests) : (object) [];
        return [
            'status' => $status,
            'phase' => $phase,
            'createdAt' => $json['createdAt'] ?? null,
            'lastUpdatedAt' => $json['lastUpdatedAt'] ?? null,
            'expiresAt' => $expiresAt,
            'reason' => $json['reason'] ?? null,
            'credentialUuid' => $json['uuid'] ?? null,
            'requests' => $requests,
            'notification' => is_object($raw->requests ?? null) ? ($raw->requests->{'notification-request'} ?? null) : null,
        ];
    }

    /**
     * Fetch the credential offer that the offer URI refers to. The offer endpoint needs no token, so none is sent,
     * and only the issuer itself is contacted.
     * @param array{baseUrl:string,token:string} $issuer
     */
    public function fetchOffer(array $issuer, string $offerUri): object
    {
        $query = [];
        // parse_url does not handle the host-less openid-credential-offer:// scheme
        $position = strpos($offerUri, '?');
        parse_str($position === false ? '' : substr($offerUri, $position + 1), $query);
        if (is_string($query['credential_offer'] ?? null)) {
            $offer = json_decode($query['credential_offer']);
            if (!is_object($offer)) {
                throw new ApiException(502, 'upstream_error', 'The offer is not valid JSON');
            }
            return $offer;
        }
        $reference = $query['credential_offer_uri'] ?? null;
        if (!is_string($reference) || !self::sameOrigin($reference, $issuer['baseUrl'])) {
            throw new ApiException(502, 'upstream_error', 'The offer does not refer to the issuer');
        }
        $response = $this->client->call('credential-offer', 'GET', $reference);
        $this->expect($response, [200], 'fetch the credential offer');
        if (!is_object($response->object)) {
            throw new ApiException(502, 'upstream_error', 'The credential offer is not valid JSON');
        }
        return $response->object;
    }

    /**
     * The open deferred transactions of the credential configuration, without the claim values of others.
     * @param array{baseUrl:string,token:string} $issuer
     * @return array{items:list<array<string,mixed>>,own:?array<string,mixed>}
     */
    public function listDeferred(array $issuer, string $credentialId, string $ownId): array
    {
        $response = $this->call($issuer, 'list-deferred', 'GET', '/api/v2/deferred');
        $this->expect($response, [200], 'list the deferred transactions');
        $list = is_array($response->json) ? $response->json : [];

        $list = array_values(array_filter($list, fn($item) => is_array($item) && is_string($item['id'] ?? null) && ($item['credential_configuration_id'] ?? null) === $credentialId));
        usort($list, fn($a, $b) => ($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0));

        $items = [];
        foreach (array_slice($list, 0, self::MAX_DETAILS) as $item) {
            $detail = $this->deferredDetail($issuer, (string) $item['id'], true);
            if ($detail === null) {
                continue; // released or expired in the meantime
            }
            $items[] = self::summarize($detail, $item['id'] === $ownId);
        }
        return ['items' => $items, 'own' => $this->deferredDetail($issuer, $ownId, true)];
    }

    /**
     * @param array{baseUrl:string,token:string} $issuer
     * @return ?array<string,mixed> null if the transaction does not exist
     */
    public function deferredDetail(array $issuer, string $id, bool $allowMissing = false): ?array
    {
        $response = $this->call($issuer, 'deferred-detail', 'GET', '/api/v2/deferred/' . rawurlencode($id));
        if ($response->status === 404 && $allowMissing) {
            return null;
        }
        $this->expect($response, [200], 'read the deferred transaction');
        $detail = $response->json;
        if (!is_array($detail)) {
            throw new ApiException(502, 'upstream_error', 'The issuer returned an unexpected transaction');
        }
        // pass the free-form claim objects on as objects: an empty one must stay {}
        $detail['data'] = $response->object->data ?? (object) [];
        $detail['claims_supplied'] = $response->object->claims_supplied ?? (object) [];
        $detail['expiresInSeconds'] = isset($detail['expiresAt']) ? (int) floor($detail['expiresAt'] / 1000 - microtime(true)) : null;
        return $detail;
    }

    /**
     * Release the credential with the given claims.
     * @param array{baseUrl:string,token:string} $issuer
     * @param array<string,string> $claims
     */
    public function authorize(array $issuer, string $id, array $claims): mixed
    {
        $response = $this->call($issuer, 'authorize', 'POST', '/api/v2/deferred/' . rawurlencode($id), ['claims' => (object) $claims]);
        $this->expectUpdate($response);
        return $response->object;
    }

    /**
     * @param array{baseUrl:string,token:string} $issuer
     */
    public function deny(array $issuer, string $id, ?string $reason): mixed
    {
        $body = ['status' => 'CREDENTIAL_DENIED'];
        if ($reason !== null && $reason !== '') {
            $body['reason'] = $reason;
        }
        $response = $this->call($issuer, 'deny', 'POST', '/api/v2/deferred/' . rawurlencode($id), $body);
        $this->expectUpdate($response);
        return $response->object;
    }

    /** @param array{baseUrl:string,token:string} $issuer */
    private function call(array $issuer, string $step, string $method, string $path, ?array $body = null): UpstreamResponse
    {
        if (($issuer['token'] ?? '') === '') {
            throw new ApiException(503, 'issuer_not_configured', 'The issuer is not configured');
        }
        return $this->client->call($step, $method, $issuer['baseUrl'] . $path, $body, $issuer['token']);
    }

    /** @param list<int> $statuses */
    private function expect(UpstreamResponse $response, array $statuses, string $what): void
    {
        if (in_array($response->status, $statuses, true)) {
            return;
        }
        error_log("issuer failed to $what: status {$response->status}");
        throw new ApiException(502, 'upstream_error', "The issuer failed to $what", ['status' => $response->status, 'body' => $response->object]);
    }

    private function expectUpdate(UpstreamResponse $response): void
    {
        if ($response->ok()) {
            return;
        }
        $detail = ['status' => $response->status, 'body' => $response->object];
        throw match ($response->status) {
            400 => new ApiException(422, 'claims_rejected', 'The issuer rejected the request', $detail),
            404 => new ApiException(404, 'not_found', 'The deferred transaction does not exist (anymore)', $detail),
            409 => new ApiException(409, 'not_open', 'The deferred transaction is no longer open', $detail),
            default => new ApiException(502, 'upstream_error', 'The issuer failed to update the transaction', $detail),
        };
    }

    /**
     * @param array<string,mixed> $detail
     * @return array<string,mixed>
     */
    private static function summarize(array $detail, bool $own): array
    {
        $known = array_merge((array) ($detail['data'] ?? []), (array) ($detail['claims_supplied'] ?? []));
        $known = array_keys(array_filter($known, fn($value) => $value !== null && $value !== ''));
        return [
            'id' => $detail['id'],
            'shortId' => substr((string) $detail['id'], 0, 8),
            'credential' => $detail['credential_configuration_id'] ?? null,
            'status' => $detail['status'] ?? null,
            'createdAt' => $detail['createdAt'] ?? null,
            'expiresAt' => $detail['expiresAt'] ?? null,
            'expiresInSeconds' => $detail['expiresInSeconds'],
            'claimsKnown' => $known,
            'proofs' => $detail['proofs'] ?? 0,
            'own' => $own,
        ];
    }

    /**
     * @param array<string,mixed> $requests
     * @return array<string,mixed>
     */
    public static function orderRequests(array $requests): array
    {
        $ordered = [];
        foreach (self::PHASE_ORDER as $phase) {
            if (array_key_exists($phase, $requests)) {
                $ordered[$phase] = $requests[$phase];
            }
        }
        return $ordered + $requests;
    }

    private static function sameOrigin(string $url, string $baseUrl): bool
    {
        $a = parse_url($url);
        $b = parse_url($baseUrl);
        if (!is_array($a) || !is_array($b) || !isset($a['host'], $b['host'])) {
            return false;
        }
        $port = fn(array $u) => $u['port'] ?? (($u['scheme'] ?? '') === 'https' ? 443 : 80);
        return strtolower($a['scheme'] ?? '') === strtolower($b['scheme'] ?? '')
            && strtolower($a['host']) === strtolower($b['host'])
            && $port($a) === $port($b);
    }
}
