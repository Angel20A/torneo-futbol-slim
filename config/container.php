<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    // Configuración general del sistema
    'settings' => function (): array {
        return require __DIR__ . '/settings.php';
    },

    // Instancia de conexión PDO hacia MariaDB
    PDO::class => function (ContainerInterface $c): PDO {
        $db = $c->get('settings')['db'];
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $db['driver'],
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );

        return new PDO(
            $dsn,
            $db['username'],
            $db['password'],
            $db['flags']
        );
    },

    // Motor de plantillas Twig
    Twig::class => function (ContainerInterface $c): Twig {
        $twigConfig = $c->get('settings')['twig'];
        $appConfig = $c->get('settings')['app'];

        $twig = Twig::create($twigConfig['path'], $twigConfig['options']);

        // Variables globales útiles en vistas
        $environment = $twig->getEnvironment();
        $environment->addGlobal('appName', $appConfig['name']);
        $environment->addGlobal('appEnv', $appConfig['env']);

        return $twig;
    },
]);

return $containerBuilder->build();
