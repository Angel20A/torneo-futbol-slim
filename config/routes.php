<?php

declare(strict_types=1);

use App\Controllers\EquipoController;
use App\Controllers\GolController;
use App\Controllers\HomeController;
use App\Controllers\JugadorController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use App\Controllers\PartidoController;
use App\Controllers\JornadaController;

return function (App $app): void {
    // Ruta Principal / Dashboard
    $app->get('/', [HomeController::class, 'index'])->setName('home');

    // ==========================================
    // Módulo de Equipos (CRUD)
    // ==========================================
    $app->group('/equipos', function (RouteCollectorProxy $group): void {
        $group->get('', [EquipoController::class, 'index'])->setName('equipos.index');
        $group->get('/crear', [EquipoController::class, 'create'])->setName('equipos.create');
        $group->post('', [EquipoController::class, 'store'])->setName('equipos.store');
        $group->get('/{id:[0-9]+}/editar', [EquipoController::class, 'edit'])->setName('equipos.edit');
        $group->post('/{id:[0-9]+}/editar', [EquipoController::class, 'update'])->setName('equipos.update');
        $group->post('/{id:[0-9]+}/eliminar', [EquipoController::class, 'delete'])->setName('equipos.delete');
    });

    // ==========================================
    // Módulo de Jugadores (CRUD)
    // ==========================================
    $app->group('/jugadores', function (RouteCollectorProxy $group): void {
        $group->get('', [JugadorController::class, 'index'])->setName('jugadores.index');
        $group->get('/crear', [JugadorController::class, 'create'])->setName('jugadores.create');
        $group->post('', [JugadorController::class, 'store'])->setName('jugadores.store');
        $group->get('/{id:[0-9]+}/editar', [JugadorController::class, 'edit'])->setName('jugadores.edit');
        $group->post('/{id:[0-9]+}/editar', [JugadorController::class, 'update'])->setName('jugadores.update');
        $group->post('/{id:[0-9]+}/eliminar', [JugadorController::class, 'delete'])->setName('jugadores.delete');
    });

    // ==========================================
    // Módulo de Partidos (CRUD + hoja del partido)
    // ==========================================
    $app->group('/partidos', function (RouteCollectorProxy $group): void {
        $group->get('', [PartidoController::class, 'index'])->setName('partidos.index');
        $group->get('/crear', [PartidoController::class, 'create'])->setName('partidos.create');
        $group->post('', [PartidoController::class, 'store'])->setName('partidos.store');
        $group->get('/{id:[0-9]+}', [PartidoController::class, 'show'])->setName('partidos.show');
        $group->get('/{id:[0-9]+}/editar', [PartidoController::class, 'edit'])->setName('partidos.edit');
        $group->post('/{id:[0-9]+}/editar', [PartidoController::class, 'update'])->setName('partidos.update');
        $group->post('/{id:[0-9]+}/eliminar', [PartidoController::class, 'delete'])->setName('partidos.delete');
    });

    // ==========================================
    // Catálogo de Jornadas (parte del módulo de Partidos)
    // ==========================================
    $app->group('/jornadas', function (RouteCollectorProxy $group): void {
        $group->get('', [JornadaController::class, 'index'])->setName('jornadas.index');
        $group->get('/crear', [JornadaController::class, 'create'])->setName('jornadas.create');
        $group->post('', [JornadaController::class, 'store'])->setName('jornadas.store');
        $group->get('/{id:[0-9]+}/editar', [JornadaController::class, 'edit'])->setName('jornadas.edit');
        $group->post('/{id:[0-9]+}/editar', [JornadaController::class, 'update'])->setName('jornadas.update');
        $group->post('/{id:[0-9]+}/eliminar', [JornadaController::class, 'delete'])->setName('jornadas.delete');
    });

    // ==========================================
    // Módulo de Goles (CRUD)
    // ==========================================
    $app->group('/goles', function (RouteCollectorProxy $group): void {
        $group->get('', [GolController::class, 'index'])->setName('goles.index');
        $group->get('/crear', [GolController::class, 'create'])->setName('goles.create');
        $group->post('', [GolController::class, 'store'])->setName('goles.store');
        $group->get('/{id:[0-9]+}/editar', [GolController::class, 'edit'])->setName('goles.edit');
        $group->post('/{id:[0-9]+}/editar', [GolController::class, 'update'])->setName('goles.update');
        $group->post('/{id:[0-9]+}/eliminar', [GolController::class, 'delete'])->setName('goles.delete');
    });

    $app->group('/incidencias', function (RouteCollectorProxy $group): void {
        // Rutas futuras para tarjetas y suspensiones
    });

    $app->group('/reportes', function (RouteCollectorProxy $group): void {
        // Rutas futuras para tablas y estadísticas
    });
};
