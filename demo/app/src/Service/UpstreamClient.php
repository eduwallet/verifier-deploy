<?php

namespace Demo\Service;

use Demo\ApiException;
use Demo\Http\Transport;

/**
 * Calls the issuer. Adds the Bearer token (when given) and records every exchange in the trace,
 * with the token redacted. The token never leaves this class.
 */
final class UpstreamClient
{
    private const MAX_TRACE_BODY = 65536;

    public function __construct(private Transport $transport, private Trace $trace)
    {
    }

    public function call(string $step, string $method, string $url, ?array $body = null, ?string $token = null): UpstreamResponse
    {
        $requestBody = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES);
        $headers = ['Accept: application/json'];
        $traceHeaders = ['Accept' => 'application/json'];
        if ($requestBody !== null) {
            $headers[] = 'Content-Type: application/json';
            $traceHeaders['Content-Type'] = 'application/json';
        }
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
            $traceHeaders['Authorization'] = 'Bearer ***';
        }

        $start = microtime(true);
        $result = $this->transport->send($method, $url, $headers, $requestBody);
        $duration = (int) round((microtime(true) - $start) * 1000);

        $json = $result['body'] === '' ? null : json_decode($result['body'], true);
        $object = $result['body'] === '' ? null : json_decode($result['body']);
        $response = new UpstreamResponse($result['status'], $result['headers'], $result['body'], $json, $object);

        $responseHeaders = $result['headers'];
        unset($responseHeaders['set-cookie']);
        $this->trace->add([
            'step' => $step,
            'at' => gmdate('c'),
            'method' => $method,
            'url' => $url,
            'requestHeaders' => $traceHeaders,
            'requestBody' => $body,
            'status' => $result['status'],
            'responseHeaders' => $responseHeaders,
            'responseBody' => $object ?? self::truncate($result['body']),
            'durationMs' => $duration,
            'error' => $result['error'],
        ]);

        if ($result['status'] === 0) {
            error_log("upstream $method $url failed: " . $result['error']);
            throw new ApiException(502, 'upstream_unreachable', 'The issuer could not be reached');
        }
        return $response;
    }

    private static function truncate(string $body): string
    {
        return strlen($body) > self::MAX_TRACE_BODY ? substr($body, 0, self::MAX_TRACE_BODY) . '...(truncated)' : $body;
    }
}
