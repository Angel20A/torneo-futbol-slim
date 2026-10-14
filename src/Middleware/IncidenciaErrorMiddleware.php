<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;
use Throwable;

final class IncidenciaErrorMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!preg_match('#^/incidencias(?:/|$)#', $request->getUri()->getPath())) {
            return $handler->handle($request);
        }

        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException) {
            return $this->message(404, 'Incidencia no encontrada.');
        } catch (HttpMethodNotAllowedException $e) {
            return $this->message(405, 'Método no permitido.')
                ->withHeader('Allow', implode(', ', $e->getAllowedMethods()));
        } catch (Throwable) {
            return $this->message(503, 'No se pudo completar la operación. Inténtalo de nuevo más tarde.');
        }
    }

    private function message(int $status, string $body): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }
}
