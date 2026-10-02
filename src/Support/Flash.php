<?php

declare(strict_types=1);

namespace App\Support;

final class Flash
{
    private const SESSION_KEY = '_flash_messages';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    public function error(string $message): void
    {
        $this->add('danger', $message);
    }

    public function warning(string $message): void
    {
        $this->add('warning', $message);
    }

    public function info(string $message): void
    {
        $this->add('info', $message);
    }

    public function add(string $type, string $message): void
    {
        $_SESSION[self::SESSION_KEY][$type][] = $message;
    }

    /**
     * Consume y retorna todos los mensajes en sesión
     *
     * @return array<string, array<string>>
     */
    public function getMessages(): array
    {
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        return $messages;
    }

    public function hasMessages(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }
}
