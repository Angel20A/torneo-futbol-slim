<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\IncidenciaController;
use App\Middleware\IncidenciaCsrfMiddleware;
use App\Support\IncidenciaCsrf;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Ruta Principal / Dashboard
    $app->get('/', [HomeController::class, 'index'])->setName('home');

    // ==========================================
    // Estructura base para módulos futuros
    // ==========================================

    // Módulo de Equipos
    $app->group('/equipos', function (RouteCollectorProxy $group): void {
        // Rutas futuras: index, create, store, show, edit, update, delete
    });

    // Módulo de Jugadores
    $app->group('/jugadores', function (RouteCollectorProxy $group): void {
        // Rutas futuras para gestión de plantilla infantil
    });

    // Módulo de Partidos / Calendario
    $app->group('/partidos', function (RouteCollectorProxy $group): void {
        // Rutas futuras para fixture, programación y resultados
    });

    // Módulo de Goles / Anotaciones
    $app->group('/goles', function (RouteCollectorProxy $group): void {
        // Rutas futuras para registro de goleadores
    });

    // Módulo de Incidencias / Tarjetas / Faltas
    $app->group('/incidencias', function (RouteCollectorProxy $group): void {
        $group->get('', [IncidenciaController::class, 'index'])->setName('incidencias.index');
        $group->get('/nueva', [IncidenciaController::class, 'create'])->setName('incidencias.create');
        $group->post('', [IncidenciaController::class, 'store'])->setName('incidencias.store');
        $group->get('/partidos/{id:[1-9][0-9]*}/jugadores', [IncidenciaController::class, 'playersForMatch'])
            ->setName('incidencias.players');
        $group->get('/{id:[1-9][0-9]*}/editar', [IncidenciaController::class, 'edit'])->setName('incidencias.edit');
        $group->post('/{id:[1-9][0-9]*}/editar', [IncidenciaController::class, 'update'])
            ->setName('incidencias.update');
        $group->post('/{id:[1-9][0-9]*}/eliminar', [IncidenciaController::class, 'delete'])
            ->setName('incidencias.delete');
    })->add(new IncidenciaCsrfMiddleware($app->getContainer()->get(IncidenciaCsrf::class)));

    // Módulo de Reportes / Tabla de Posiciones / Estadísticas
    $app->group('/reportes', function (RouteCollectorProxy $group): void {
        // Rutas futuras para reportes y tablas del torneo
    });
};
