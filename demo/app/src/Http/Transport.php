<?php

namespace Demo\Http;

interface Transport
{
    /**
     * @param list<string> $headers "Name: value" lines
     * @return array{status:int,headers:array<string,string>,body:string,error:?string} status 0 on a transport error
     */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}
