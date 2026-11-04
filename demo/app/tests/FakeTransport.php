<?php

namespace Demo\Tests;

use Demo\Http\Transport;

/** Scripted issuer: responses are registered per "METHOD url", every call is recorded. */
final class FakeTransport implements Transport
{
    /** @var array<string,array{status:int,body:mixed}> */
    private array $responses = [];
    /** @var list<array{method:string,url:string,headers:list<string>,body:?string}> */
    public array $calls = [];

    public function on(string $method, string $url, mixed $body, int $status = 200): self
    {
        $this->responses["$method $url"] = ['status' => $status, 'body' => $body];
        return $this;
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $response = $this->responses["$method $url"] ?? null;
        if ($response === null) {
            return ['status' => 0, 'headers' => [], 'body' => '', 'error' => "no response scripted for $method $url"];
        }
        return [
            'status' => $response['status'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode($response['body']),
            'error' => null,
        ];
    }
}
