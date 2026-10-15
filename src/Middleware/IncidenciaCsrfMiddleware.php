<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\IncidenciaCsrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class IncidenciaCsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly IncidenciaCsrf $csrf)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();
            $token = is_array($body) ? ($body['_csrf'] ?? null) : null;
            if (!$this->csrf->validToken($token)) {
                $response = new Response(403);
                $response->getBody()->write('Solicitud no válida. Recarga el formulario e inténtalo de nuevo.');

                return $response->withHeader('Content-Type', 'text/plain; charset=UTF-8');
            }
        }

        return $handler->handle($request);
    }
}
