<?php

declare(strict_types=1);

namespace App\Support;

use BadMethodCallException;

final class Flash
{
    public const SESSION_KEY = '_flash_messages';

    /**
     * Cache local en memoria para retener los mensajes durante la renderización
     * de la misma petición una vez que han sido consumidos de la sesión.
     *
     * @var array<string, list<string>>|null
     */
    private ?array $consumedMessages = null;

    /**
     * Registra un mensaje de éxito.
     */
    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    /**
     * Registra un mensaje de error (mapeado automáticamente a 'danger' para Bootstrap 5).
     */
    public function error(string $message): void
    {
        $this->add('danger', $message);
    }

    /**
     * Registra un mensaje de advertencia.
     */
    public function warning(string $message): void
    {
        $this->add('warning', $message);
    }

    /**
     * Registra un mensaje de información.
     */
    public function info(string $message): void
    {
        $this->add('info', $message);
    }

    /**
     * Agrega un mensaje a una categoría flash específica.
     */
    public function add(string $type, string $message): void
    {
        $this->ensureSessionActive();

        $key = ($type === 'error') ? 'danger' : $type;

        $_SESSION[self::SESSION_KEY][$key][] = $message;

        // Si ya se habían consumido los mensajes en esta petición, actualizamos la memoria local
        if ($this->consumedMessages !== null) {
            $this->consumedMessages[$key][] = $message;
        }
    }

    /**
     * Recupera todos los mensajes almacenados y los elimina inmediatamente de la sesión,
     * garantizando que solo vivan durante una sola petición HTTP (ciclo flash).
     *
     * @return array<string, list<string>>
     */
    public function getMessages(): array
    {
        $this->ensureSessionActive();

        if ($this->consumedMessages !== null) {
            return $this->consumedMessages;
        }

        /** @var array<string, list<string>> $messages */
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        $this->consumedMessages = $messages;

        return $this->consumedMessages;
    }

    /**
     * Verifica si existen mensajes pendientes (opcionalmente filtrando por tipo).
     */
    public function hasMessages(?string $type = null): bool
    {
        $this->ensureSessionActive();

        $targetKey = ($type !== null && $type === 'error') ? 'danger' : $type;

        if ($this->consumedMessages !== null) {
            if ($targetKey !== null) {
                return !empty($this->consumedMessages[$targetKey]);
            }

            return !empty($this->consumedMessages);
        }

        if ($targetKey !== null) {
            return !empty($_SESSION[self::SESSION_KEY][$targetKey]);
        }

        return !empty($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Permite invocaciones estáticas opcionales (ej: Flash::success('...')).
     *
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $instance = new self();
        if (method_exists($instance, $name)) {
            return $instance->$name(...$arguments);
        }

        throw new BadMethodCallException(
            sprintf('El método %s::%s() no existe.', self::class, $name)
        );
    }

    /**
     * Garantiza de forma defensiva que la sesión esté iniciada si aún no lo estuviera.
     */
    private function ensureSessionActive(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
}
