<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class IncidenciaCsrf
{
    private const SESSION_KEY = 'incidencias_csrf_token';

    public function token(): string
    {
        $this->requireSession();
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public function validToken(mixed $submitted): bool
    {
        $this->requireSession();

        return is_string($submitted)
            && isset($_SESSION[self::SESSION_KEY])
            && is_string($_SESSION[self::SESSION_KEY])
            && hash_equals($_SESSION[self::SESSION_KEY], $submitted);
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('No hay una sesión activa.');
        }
    }
}
