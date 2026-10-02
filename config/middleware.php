<?php

declare(strict_types=1);

use App\Middleware\SessionMiddleware;
use Slim\App;
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

    // 4. Middleware de ciclo de vida seguro de Sesiones
    // Al registrarse aquí (orden LIFO), se ejecuta antes del enrutamiento y después del ErrorMiddleware
    $app->add(SessionMiddleware::class);

    // 5. Middleware de captura de excepciones y errores (debe ser el más externo en ejecución)
    $app->addErrorMiddleware(
        $settings['app']['debug'],
        true,
        true
    );
};
