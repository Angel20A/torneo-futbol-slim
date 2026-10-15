<?php

declare(strict_types=1);

namespace App\Support;

use BadMethodCallException;

final class Flash
{
    public const SESSION_KEY = '_flash_messages';

    private ?array $consumedMessages = null;

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
        $this->ensureSessionActive();
        $key = ($type === 'error') ? 'danger' : $type;
        $_SESSION[self::SESSION_KEY][$key][] = $message;

        if ($this->consumedMessages !== null) {
            $this->consumedMessages[$key][] = $message;
        }
    }

    public function getMessages(): array
    {
        $this->ensureSessionActive();
        if ($this->consumedMessages !== null) {
            return $this->consumedMessages;
        }

        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);
        $this->consumedMessages = $messages;

        return $this->consumedMessages;
    }

    public function hasMessages(?string $type = null): bool
    {
        $this->ensureSessionActive();
        $targetKey = ($type === 'error') ? 'danger' : $type;

        if ($this->consumedMessages !== null) {
            return $targetKey === null
                ? !empty($this->consumedMessages)
                : !empty($this->consumedMessages[$targetKey]);
        }

        return $targetKey === null
            ? !empty($_SESSION[self::SESSION_KEY])
            : !empty($_SESSION[self::SESSION_KEY][$targetKey]);
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        $instance = new self();
        if (method_exists($instance, $name)) {
            return $instance->$name(...$arguments);
        }

        throw new BadMethodCallException(sprintf('El método %s::%s() no existe.', self::class, $name));
    }

    private function ensureSessionActive(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
}
