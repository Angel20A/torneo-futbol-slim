<?php

declare(strict_types=1);

use App\Repositories\EquipoRepository;
use App\Repositories\JugadorRepository;
use App\Services\UploadService;
use App\Support\Flash;
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

    // Servicio de mensajes Flash
    Flash::class => function (): Flash {
        return new Flash();
    },

    // Servicio de subida de archivos
    UploadService::class => function (): UploadService {
        return new UploadService(dirname(__DIR__) . '/public');
    },

    // Repositorios
    EquipoRepository::class => function (ContainerInterface $c): EquipoRepository {
        return new EquipoRepository($c->get(PDO::class));
    },

    JugadorRepository::class => function (ContainerInterface $c): JugadorRepository {
        return new JugadorRepository($c->get(PDO::class));
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
