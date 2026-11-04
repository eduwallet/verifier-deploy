<?php

namespace Demo\Service;

final class UpstreamResponse
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly mixed $json,
        /** the same JSON decoded to objects: empty objects stay `{}` when passed on to the front-end */
        public readonly mixed $object = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
