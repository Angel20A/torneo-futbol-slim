<?php

declare(strict_types=1);

use App\Controllers\EquipoController;
use App\Controllers\GolController;
use App\Controllers\HomeController;
use App\Controllers\JugadorController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

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
    // Módulos Futuros
    // ==========================================
    $app->group('/partidos', function (RouteCollectorProxy $group): void {
        // Rutas futuras para fixture y programación
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
