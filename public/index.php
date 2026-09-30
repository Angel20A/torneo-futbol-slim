<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Inicializar variables de entorno (.env)
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Instanciar Contenedor de Inyección de Dependencias (PHP-DI)
$container = require __DIR__ . '/../config/container.php';
AppFactory::setContainer($container);

// Inicializar aplicación Slim
$app = AppFactory::create();

// Registrar Middlewares y Rutas
(require __DIR__ . '/../config/middleware.php')($app);
(require __DIR__ . '/../config/routes.php')($app);

// Despachar la petición HTTP
$app->run();
