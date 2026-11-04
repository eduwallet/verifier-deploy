<?php

namespace Demo\Http;

/** Upstream HTTP with TLS verification, timeouts, no redirects and a limit on the response size. */
final class CurlTransport implements Transport
{
    private const MAX_RESPONSE = 1048576;

    public function __construct(private int $connectTimeout = 5, private int $timeout = 20)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $responseHeaders = [];
        $content = '';
        $tooLarge = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$content, &$tooLarge): int {
                $content .= $chunk;
                if (strlen($content) > self::MAX_RESPONSE) {
                    $tooLarge = true;
                    return 0;
                }
                return strlen($chunk);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $ok === false ? ($tooLarge ? 'response too large' : curl_error($ch)) : null;
        curl_close($ch);
        return ['status' => $error === null ? $status : 0, 'headers' => $responseHeaders, 'body' => $content, 'error' => $error];
    }
}
