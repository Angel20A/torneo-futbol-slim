<?php

declare(strict_types=1);

use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app): void {
    /** @var array $settings */
    $settings = $app->getContainer()->get('settings');

    // Analiza peticiones application/x-www-form-urlencoded y application/json
    $app->addBodyParsingMiddleware();

    // Twig Middleware: asocia el motor de plantillas y provee extensiones a las vistas
    $app->add(TwigMiddleware::createFromContainer($app, Twig::class));

    // Middleware de enrutamiento Slim
    $app->addRoutingMiddleware();

    // Middleware de manejo de errores
    $app->addErrorMiddleware(
        $settings['app']['debug'],
        true,
        true
    );
};
