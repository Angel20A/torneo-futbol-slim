<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    /**
     * @var array{
     *     lifetime: int,
     *     path: string,
     *     domain: string,
     *     secure: bool,
     *     httponly: bool,
     *     samesite: string
     * }
     */
    private array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $this->options = [
            'lifetime' => (int) ($options['lifetime'] ?? 0),
            'path'     => (string) ($options['path'] ?? '/'),
            'domain'   => (string) ($options['domain'] ?? ''),
            'secure'   => (bool) ($options['secure'] ?? $isHttps),
            'httponly' => (bool) ($options['httponly'] ?? true),
            'samesite' => (string) ($options['samesite'] ?? 'Lax'),
        ];
    }

    /**
     * Procesa la petición HTTP gestionando el ciclo de vida de la sesión de manera segura.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. Iniciar sesión únicamente si no está activa y no se han enviado cabeceras
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_set_cookie_params([
                'lifetime' => $this->options['lifetime'],
                'path'     => $this->options['path'],
                'domain'   => $this->options['domain'],
                'secure'   => $this->options['secure'],
                'httponly' => $this->options['httponly'],
                'samesite' => $this->options['samesite'],
            ]);

            session_start();
        }

        try {
            // 2. Procesar el siguiente middleware o controlador
            $response = $handler->handle($request);
        } finally {
            // 3. Cerrar la escritura de la sesión de forma limpia para evitar bloqueos por concurrencia
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }

        return $response;
    }
}
