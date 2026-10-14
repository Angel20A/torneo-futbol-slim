<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GolRepository;
use App\Repositories\JugadorRepository;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

final class GolController
{
    public function __construct(
        private readonly Twig $view,
        private readonly GolRepository $goles,
        private readonly JugadorRepository $jugadores,
        private readonly Flash $flash
    ) {
    }

    /**
     * Listado general de goles registrados con filtros y tarjetas de estadísticas
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idPartido = !empty($params['id_partido']) ? (int) $params['id_partido'] : null;
        $idJugador = !empty($params['id_jugador']) ? (int) $params['id_jugador'] : null;

        $listado = $this->goles->all($idPartido, $idJugador);
        $partidos = $this->goles->getPartidos();
        $listaJugadores = $this->goles->getJugadores();
        $estadisticas = $this->goles->getEstadisticas();

        return $this->view->render($response, 'goles/index.twig', [
            'title' => 'Gestión y Control de Goleo',
            'goles' => $listado,
            'partidos' => $partidos,
            'jugadores' => $listaJugadores,
            'selected_partido' => $idPartido,
            'selected_jugador' => $idJugador,
            'stats' => $estadisticas,
        ]);
    }

    /**
     * Formulario para registrar una nueva anotación
     */
    public function create(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idPartido = !empty($params['id_partido']) ? (int) $params['id_partido'] : null;

        $partidos = $this->goles->getPartidos();
        $listaJugadores = $this->goles->getJugadores();

        return $this->view->render($response, 'goles/form.twig', [
            'title' => 'Registrar Nueva Anotación',
            'gol' => $idPartido ? ['id_partido' => $idPartido, 'cantidad' => 1] : null,
            'partidos' => $partidos,
            'jugadores' => $listaJugadores,
            'errors' => [],
        ]);
    }

    /**
     * Almacena una nueva anotación en la base de datos
     */
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $idPartido = (int) ($data['id_partido'] ?? 0);
        $idJugador = (int) ($data['id_jugador'] ?? 0);
        $cantidad = (int) ($data['cantidad'] ?? 0);

        $errors = $this->validate($idJugador, $idPartido, $cantidad);

        if (!empty($errors)) {
            return $this->view->render($response, 'goles/form.twig', [
                'title' => 'Registrar Nueva Anotación',
                'gol' => [
                    'id_partido' => $idPartido,
                    'id_jugador' => $idJugador,
                    'cantidad' => $cantidad,
                ],
                'partidos' => $this->goles->getPartidos(),
                'jugadores' => $this->goles->getJugadores(),
                'errors' => $errors,
            ]);
        }

        try {
            $this->goles->create($idJugador, $idPartido, $cantidad);
            $this->flash->success("Se han registrado {$cantidad} gol(es) satisfactoriamente.");

            return $response->withHeader('Location', '/goles')->withStatus(302);
        } catch (Throwable $e) {
            $errors[] = 'Error al registrar la anotación: ' . $e->getMessage();

            return $this->view->render($response, 'goles/form.twig', [
                'title' => 'Registrar Nueva Anotación',
                'gol' => [
                    'id_partido' => $idPartido,
                    'id_jugador' => $idJugador,
                    'cantidad' => $cantidad,
                ],
                'partidos' => $this->goles->getPartidos(),
                'jugadores' => $this->goles->getJugadores(),
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Formulario para editar una anotación existente
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $gol = $this->goles->findById($id);

        if (!$gol) {
            $this->flash->error('El registro de gol seleccionado no existe.');
            return $response->withHeader('Location', '/goles')->withStatus(302);
        }

        return $this->view->render($response, 'goles/form.twig', [
            'title' => "Editar Anotación: {$gol['jugador_nombres']} {$gol['jugador_apellidos']}",
            'gol' => $gol,
            'partidos' => $this->goles->getPartidos(),
            'jugadores' => $this->goles->getJugadores(),
            'errors' => [],
        ]);
    }

    /**
     * Actualiza la información de una anotación
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $gol = $this->goles->findById($id);

        if (!$gol) {
            $this->flash->error('El registro de gol seleccionado no existe.');
            return $response->withHeader('Location', '/goles')->withStatus(302);
        }

        $data = (array) $request->getParsedBody();
        $idPartido = (int) ($data['id_partido'] ?? 0);
        $idJugador = (int) ($data['id_jugador'] ?? 0);
        $cantidad = (int) ($data['cantidad'] ?? 0);

        $errors = $this->validate($idJugador, $idPartido, $cantidad, $id);

        if (!empty($errors)) {
            return $this->view->render($response, 'goles/form.twig', [
                'title' => "Editar Anotación: {$gol['jugador_nombres']} {$gol['jugador_apellidos']}",
                'gol' => array_merge($gol, [
                    'id_partido' => $idPartido,
                    'id_jugador' => $idJugador,
                    'cantidad' => $cantidad,
                ]),
                'partidos' => $this->goles->getPartidos(),
                'jugadores' => $this->goles->getJugadores(),
                'errors' => $errors,
            ]);
        }

        try {
            $this->goles->update($id, $idJugador, $idPartido, $cantidad);
            $this->flash->success('La anotación fue actualizada correctamente.');

            return $response->withHeader('Location', '/goles')->withStatus(302);
        } catch (Throwable $e) {
            $errors[] = 'Error al actualizar la anotación: ' . $e->getMessage();

            return $this->view->render($response, 'goles/form.twig', [
                'title' => "Editar Anotación: {$gol['jugador_nombres']} {$gol['jugador_apellidos']}",
                'gol' => array_merge($gol, [
                    'id_partido' => $idPartido,
                    'id_jugador' => $idJugador,
                    'cantidad' => $cantidad,
                ]),
                'partidos' => $this->goles->getPartidos(),
                'jugadores' => $this->goles->getJugadores(),
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Elimina un registro de anotación
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $gol = $this->goles->findById($id);

        if (!$gol) {
            $this->flash->error('El registro de gol no fue encontrado.');
            return $response->withHeader('Location', '/goles')->withStatus(302);
        }

        try {
            $this->goles->delete($id);
            $this->flash->success("Se eliminó el registro de goles de {$gol['jugador_nombres']} {$gol['jugador_apellidos']} satisfactoriamente.");
        } catch (Throwable $e) {
            $this->flash->error('No se pudo eliminar el registro: ' . $e->getMessage());
        }

        return $response->withHeader('Location', '/goles')->withStatus(302);
    }

    /**
     * Valida reglas de negocio para anotaciones de gol
     *
     * @return array<string>
     */
    private function validate(int $idJugador, int $idPartido, int $cantidad, ?int $excludeId = null): array
    {
        $errors = [];

        if ($idPartido <= 0) {
            $errors[] = 'Debe seleccionar un partido válido.';
        }

        if ($idJugador <= 0) {
            $errors[] = 'Debe seleccionar un jugador válido.';
        }

        if ($cantidad < 1) {
            $errors[] = 'La cantidad de goles debe ser de al menos 1.';
        }

        // Si ambos identificadores son válidos, aplicamos reglas de integridad y dominio
        if ($idPartido > 0 && $idJugador > 0) {
            // 1. Regla de dominio: El jugador debe pertenecer a uno de los dos equipos del partido
            if (!$this->goles->isJugadorEnPartido($idJugador, $idPartido)) {
                $errors[] = 'El jugador seleccionado no pertenece a ninguno de los dos clubes participantes en este encuentro.';
            }

            // 2. Regla de integridad: UNIQUE(id_jugador, id_partido)
            if ($this->goles->existsJugadorPartido($idJugador, $idPartido, $excludeId)) {
                $errors[] = 'Ya existe un registro de goles para este jugador en el partido seleccionado. Puede editar el registro existente para ajustar la cantidad.';
            }
        }

        return $errors;
    }
}
