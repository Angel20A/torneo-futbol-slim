<?php

declare(strict_types=1);

use App\Middleware\SessionMiddleware;
use Slim\App;
use App\Middleware\IncidenciaErrorMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app): void {
    /** @var array $settings */
    $settings = $app->getContainer()->get('settings');

    // 1. Analiza peticiones application/x-www-form-urlencoded y application/json
    $app->addBodyParsingMiddleware();

    // 2. Twig Middleware: asocia el motor de plantillas y provee extensiones a las vistas
    $app->add(TwigMiddleware::createFromContainer($app, Twig::class));

    // 3. Middleware de enrutamiento de Slim
    $app->addRoutingMiddleware();

    // Respuestas genéricas solo para las rutas de Incidencias, incluso si no coinciden con el patrón de ID.
    $app->add(new IncidenciaErrorMiddleware());

    // Orden LIFO: inicia la sesión antes del enrutamiento y la cierra después de la respuesta.
    $app->add(new SessionMiddleware([
        'save_path' => dirname(__DIR__) . '/var/sessions',
    ]));

    // Middleware de manejo de errores
    $app->addErrorMiddleware(
        $settings['app']['debug'],
        true,
        true
    );
};
