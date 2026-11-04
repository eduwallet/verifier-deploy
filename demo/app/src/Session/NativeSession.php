<?php

namespace Demo\Session;

/** PHP file based session with a strict cookie. */
final class NativeSession implements SessionInterface
{
    private bool $open = false;

    public function __construct(private bool $secureCookie)
    {
        $this->start();
    }

    private function start(): void
    {
        if ($this->open) {
            return;
        }
        session_name('demo_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secureCookie,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        if (!session_start()) {
            throw new \RuntimeException('The session could not be started');
        }
        $this->open = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function close(): void
    {
        if ($this->open) {
            session_write_close();
            $this->open = false;
        }
    }
}
