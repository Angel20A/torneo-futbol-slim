<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class SessionMiddleware implements MiddlewareInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $this->options = [
            'lifetime' => (int) ($options['lifetime'] ?? 0),
            'path' => (string) ($options['path'] ?? '/'),
            'domain' => (string) ($options['domain'] ?? ''),
            'secure' => (bool) ($options['secure'] ?? $isHttps),
            'httponly' => (bool) ($options['httponly'] ?? true),
            'samesite' => (string) ($options['samesite'] ?? 'Lax'),
            'save_path' => (string) ($options['save_path'] ?? ''),
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (headers_sent()) {
                throw new RuntimeException('No se pudo iniciar la sesión.');
            }

            if ($this->options['save_path'] !== '') {
                $savePath = $this->options['save_path'];
                if (!is_dir($savePath) && !@mkdir($savePath, 0700, true) && !is_dir($savePath)) {
                    throw new RuntimeException('No se pudo iniciar la sesión.');
                }
                if (!is_writable($savePath)) {
                    throw new RuntimeException('No se pudo iniciar la sesión.');
                }
                session_save_path($savePath);
            }

            session_set_cookie_params([
                'lifetime' => $this->options['lifetime'],
                'path' => $this->options['path'],
                'domain' => $this->options['domain'],
                'secure' => $this->options['secure'],
                'httponly' => $this->options['httponly'],
                'samesite' => $this->options['samesite'],
            ]);

            if (!@session_start()) {
                throw new RuntimeException('No se pudo iniciar la sesión.');
            }
        }

        try {
            return $handler->handle($request);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }
}
