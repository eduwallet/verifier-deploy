<?php

namespace Demo\Session;

interface SessionInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    /** Release the session lock, so that long upstream calls do not block other requests of the visitor. */
    public function close(): void;
}
