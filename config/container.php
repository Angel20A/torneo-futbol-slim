<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use App\Repositories\IncidenciaRepository;
use App\Support\Flash;
use App\Support\IncidenciaCsrf;
use App\Validation\IncidenciaValidator;
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

    // Componentes compartidos y dependencias del módulo de Incidencias
    Flash::class => function (): Flash {
        return new Flash();
    },

    IncidenciaCsrf::class => function (): IncidenciaCsrf {
        return new IncidenciaCsrf();
    },

    IncidenciaRepository::class => function (ContainerInterface $c): IncidenciaRepository {
        return new IncidenciaRepository($c->get(PDO::class));
    },

    IncidenciaValidator::class => function (): IncidenciaValidator {
        return new IncidenciaValidator();
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
        $environment->addGlobal('flash', $c->get(Flash::class));

        return $twig;
    },
]);

return $containerBuilder->build();
