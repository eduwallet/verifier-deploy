<?php

namespace Demo\Session;

/** In-memory session, for tests. */
final class ArraySession implements SessionInterface
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function close(): void
    {
    }
}
