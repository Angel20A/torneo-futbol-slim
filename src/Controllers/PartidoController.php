<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EquipoRepository;
use App\Repositories\JornadaRepository;
use App\Repositories\PartidoRepository;
use App\Support\Flash;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

final class PartidoController
{
    public function __construct(
        private readonly Twig $view,
        private readonly PartidoRepository $partidos,
        private readonly JornadaRepository $jornadas,
        private readonly EquipoRepository $equipos,
        private readonly Flash $flash
    ) {
    }

    /**
     * Listado de partidos agrupado por jornada, con filtros opcionales
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idJornada = !empty($params['id_jornada']) ? (int) $params['id_jornada'] : null;
        $idEquipo = !empty($params['id_equipo']) ? (int) $params['id_equipo'] : null;

        $listaJornadas = $this->jornadas->all();
        $listado = $this->partidos->all($idJornada, $idEquipo);

        // Una tarjeta por jornada (incluso las vacías, para poder programar desde ahí)
        $grupos = [];
        foreach ($listaJornadas as $jornada) {
            if ($idJornada !== null && (int) $jornada['id_jornada'] !== $idJornada) {
                continue;
            }
            $grupos[(int) $jornada['id_jornada']] = ['jornada' => $jornada, 'partidos' => []];
        }

        foreach ($listado as $partido) {
            $grupos[(int) $partido['id_jornada']]['partidos'][] = $this->conEstado($partido);
        }

        // Si se filtra por equipo, solo se muestran las jornadas donde juega
        if ($idEquipo !== null) {
            $grupos = array_filter($grupos, static fn (array $g): bool => $g['partidos'] !== []);
        }

        return $this->view->render($response, 'partidos/index.twig', [
            'title' => 'Gestión de Partidos',
            'grupos' => array_values($grupos),
            'jornadas' => $listaJornadas,
            'equipos' => $this->equipos->all(),
            'selected_jornada' => $idJornada,
            'selected_equipo' => $idEquipo,
            'total_partidos' => count($listado),
        ]);
    }

    /**
     * Hoja del partido: marcador, planteles con suspendidos y tarjetas
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $partido = $this->partidos->findById($id);

        if ($partido === null) {
            $this->flash->error('El partido seleccionado no existe.');
            return $response->withHeader('Location', '/partidos')->withStatus(302);
        }

        $partido = $this->conEstado($partido);
        $idJornada = (int) $partido['id_jornada'];
        $fecha = (string) $partido['fecha'];

        $plantelLocal = $this->partidos->plantel((int) $partido['id_equipo_local'], $idJornada, $fecha, $id);
        $plantelVisita = $this->partidos->plantel((int) $partido['id_equipo_visita'], $idJornada, $fecha, $id);

        $suspendidos = count(array_filter(
            array_merge($plantelLocal, $plantelVisita),
            static fn (array $j): bool => $j['suspendido']
        ));

        return $this->view->render($response, 'partidos/show.twig', [
            'title' => "{$partido['local_nombre']} vs {$partido['visita_nombre']}",
            'partido' => $partido,
            'plantel_local' => $plantelLocal,
            'plantel_visita' => $plantelVisita,
            'incidencias' => $this->partidos->incidenciasDePartido($id),
            'suspendidos' => $suspendidos,
        ]);
    }

    /**
     * Formulario para programar un partido (acepta ?id_jornada= para preseleccionar)
     */
    public function create(Request $request, Response $response): Response
    {
        $idJornada = (int) ($request->getQueryParams()['id_jornada'] ?? 0);

        return $this->renderForm($response, 'Programar Nuevo Partido', ['id_jornada' => $idJornada], [], null);
    }

    /**
     * Guarda un nuevo partido. La fecha se toma de la jornada elegida.
     */
    public function store(Request $request, Response $response): Response
    {
        [$idJornada, $idLocal, $idVisita] = $this->readInput($request);

        $jornada = $this->jornadas->findById($idJornada);
        $errors = $this->validate($jornada, $idLocal, $idVisita);

        $formData = [
            'id_jornada' => $idJornada,
            'id_equipo_local' => $idLocal,
            'id_equipo_visita' => $idVisita,
        ];

        if ($errors !== [] || $jornada === null) {
            return $this->renderForm($response, 'Programar Nuevo Partido', $formData, $errors, null);
        }

        try {
            $idNuevo = $this->partidos->create($idJornada, $idLocal, $idVisita, (string) $jornada['fecha_juego']);
            $creado = $this->partidos->findById($idNuevo);

            $this->flash->success(sprintf(
                'Partido %s vs %s programado en la Jornada %d.',
                $creado['local_nombre'] ?? 'Local',
                $creado['visita_nombre'] ?? 'Visitante',
                (int) $jornada['numero_jornada']
            ));
            $this->emitirAvisos($idLocal, $idVisita, $idNuevo);

            return $response->withHeader('Location', '/partidos')->withStatus(302);
        } catch (Throwable $e) {
            $errors[] = 'Error al programar el partido: ' . $e->getMessage();

            return $this->renderForm($response, 'Programar Nuevo Partido', $formData, $errors, null);
        }
    }

    /**
     * Formulario de edición de un partido
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $partido = $this->partidos->findById($id);

        if ($partido === null) {
            $this->flash->error('El partido seleccionado no existe.');
            return $response->withHeader('Location', '/partidos')->withStatus(302);
        }

        return $this->renderForm(
            $response,
            $this->tituloEdicion($partido),
            $partido,
            [],
            $id,
            $this->tieneRegistros($partido)
        );
    }

    /**
     * Actualiza un partido existente
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $partido = $this->partidos->findById($id);

        if ($partido === null) {
            $this->flash->error('El partido seleccionado no existe.');
            return $response->withHeader('Location', '/partidos')->withStatus(302);
        }

        [$idJornada, $idLocal, $idVisita] = $this->readInput($request);

        $jornada = $this->jornadas->findById($idJornada);
        $errors = $this->validate($jornada, $idLocal, $idVisita, $id, $partido);
        $bloqueado = $this->tieneRegistros($partido);

        $formData = array_merge($partido, [
            'id_jornada' => $idJornada,
            'id_equipo_local' => $idLocal,
            'id_equipo_visita' => $idVisita,
        ]);

        if ($errors !== [] || $jornada === null) {
            return $this->renderForm($response, $this->tituloEdicion($partido), $formData, $errors, $id, $bloqueado);
        }

        try {
            $this->partidos->update($id, $idJornada, $idLocal, $idVisita, (string) $jornada['fecha_juego']);
            $this->flash->success('El partido fue actualizado correctamente.');
            $this->emitirAvisos($idLocal, $idVisita, $id);

            return $response->withHeader('Location', '/partidos')->withStatus(302);
        } catch (Throwable $e) {
            $errors[] = 'Error al actualizar el partido: ' . $e->getMessage();

            return $this->renderForm($response, $this->tituloEdicion($partido), $formData, $errors, $id, $bloqueado);
        }
    }

    /**
     * Elimina un partido solo si no tiene goles ni incidencias asociados
     * (la BD los borraría en cascada y se perderían suspensiones y goleadores).
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $partido = $this->partidos->findById($id);

        if ($partido === null) {
            $this->flash->error('El partido no fue encontrado.');
            return $response->withHeader('Location', '/partidos')->withStatus(302);
        }

        if ($this->tieneRegistros($partido)) {
            $this->flash->error(sprintf(
                'No es posible eliminar el partido %s vs %s porque tiene %d registro(s) de goles y %d incidencia(s). Elimine esos registros primero.',
                $partido['local_nombre'],
                $partido['visita_nombre'],
                $partido['total_registros_gol'],
                $partido['total_incidencias']
            ));
            return $response->withHeader('Location', '/partidos')->withStatus(302);
        }

        try {
            $this->partidos->delete($id);
            $this->flash->success("Partido {$partido['local_nombre']} vs {$partido['visita_nombre']} eliminado satisfactoriamente.");
        } catch (Throwable $e) {
            $this->flash->error('No se pudo eliminar el partido: ' . $e->getMessage());
        }

        return $response->withHeader('Location', '/partidos')->withStatus(302);
    }

    /**
     * Renderiza el formulario con los catálogos que necesita
     *
     * @param array<string, mixed>|null $partido
     * @param array<string> $errors
     */
    private function renderForm(
        Response $response,
        string $title,
        ?array $partido,
        array $errors,
        ?int $excluirId,
        bool $equiposBloqueados = false
    ): Response {
        return $this->view->render($response, 'partidos/form.twig', [
            'title' => $title,
            'partido' => $partido,
            'equipos' => $this->equipos->all(),
            'jornadas' => $this->jornadas->all(),
            'ocupados' => $this->partidos->ocupadosPorJornada($excluirId),
            'equipos_bloqueados' => $equiposBloqueados,
            'errors' => $errors,
        ]);
    }

    /**
     * Lee jornada, equipo local y equipo visitante del cuerpo de la petición
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function readInput(Request $request): array
    {
        $data = (array) $request->getParsedBody();

        return [
            (int) ($data['id_jornada'] ?? 0),
            (int) ($data['id_equipo_local'] ?? 0),
            (int) ($data['id_equipo_visita'] ?? 0),
        ];
    }

    /**
     * Reglas de negocio de un partido
     *
     * @param array<string, mixed>|null $jornada
     * @param array<string, mixed>|null $partidoActual Partido que se está editando (si aplica)
     * @return array<string>
     */
    private function validate(
        ?array $jornada,
        int $idLocal,
        int $idVisita,
        ?int $excluirId = null,
        ?array $partidoActual = null
    ): array {
        $errors = [];

        if ($jornada === null) {
            $errors[] = 'Debe seleccionar una jornada válida.';
        }

        $local = $idLocal > 0 ? $this->equipos->findById($idLocal) : null;
        $visita = $idVisita > 0 ? $this->equipos->findById($idVisita) : null;

        if ($local === null) {
            $errors[] = 'Debe seleccionar un equipo local válido.';
        }

        if ($visita === null) {
            $errors[] = 'Debe seleccionar un equipo visitante válido.';
        }

        if ($local !== null && $visita !== null && $idLocal === $idVisita) {
            $errors[] = 'El equipo local y el visitante no pueden ser el mismo.';
        }

        if ($errors !== [] || $jornada === null || $local === null || $visita === null) {
            return $errors;
        }

        // Si ya hay goles o tarjetas, cambiar los equipos dejaría registros de jugadores ajenos al partido
        if (
            $partidoActual !== null
            && $this->tieneRegistros($partidoActual)
            && ((int) $partidoActual['id_equipo_local'] !== $idLocal || (int) $partidoActual['id_equipo_visita'] !== $idVisita)
        ) {
            return ['No se pueden cambiar los equipos porque el partido ya tiene goles o incidencias registrados.'];
        }

        // Un equipo solo puede jugar una vez por jornada
        foreach ([[$idLocal, $local], [$idVisita, $visita]] as [$idEquipo, $equipo]) {
            $ocupado = $this->partidos->partidoDeEquipoEnJornada((int) $jornada['id_jornada'], $idEquipo, $excluirId);
            if ($ocupado !== null) {
                $errors[] = "El equipo '{$equipo['nombre']}' ya tiene un partido en la Jornada {$jornada['numero_jornada']}. Un equipo solo puede jugar una vez por jornada.";
            }
        }

        return $errors;
    }

    /**
     * Avisos no bloqueantes tras guardar: equipos sin jugadores y enfrentamientos repetidos
     */
    private function emitirAvisos(int $idLocal, int $idVisita, ?int $excluirId): void
    {
        foreach ([$idLocal, $idVisita] as $idEquipo) {
            $equipo = $this->equipos->findById($idEquipo);
            if ($equipo !== null && (int) $equipo['total_jugadores'] === 0) {
                $this->flash->warning("El equipo '{$equipo['nombre']}' aún no tiene jugadores registrados.");
            }
        }

        $previos = $this->partidos->jornadasDeEnfrentamiento($idLocal, $idVisita, $excluirId);
        if ($previos !== []) {
            $this->flash->warning(
                'Estos equipos ya se enfrentan en la(s) jornada(s): ' . implode(', ', $previos) . '. Verifique que no sea un partido duplicado.'
            );
        }
    }

    /**
     * Agrega el estado calculado por fecha: programado, hoy o jugado
     *
     * @param array<string, mixed> $partido
     * @return array<string, mixed>
     */
    private function conEstado(array $partido): array
    {
        $hoy = (new DateTimeImmutable('today', new DateTimeZone('America/Guatemala')))->format('Y-m-d');
        $fecha = (string) $partido['fecha'];

        $partido['estado'] = $fecha > $hoy ? 'programado' : ($fecha === $hoy ? 'hoy' : 'jugado');

        return $partido;
    }

    /**
     * @param array<string, mixed> $partido
     */
    private function tieneRegistros(array $partido): bool
    {
        return ((int) $partido['total_registros_gol'] + (int) $partido['total_incidencias']) > 0;
    }

    /**
     * @param array<string, mixed> $partido
     */
    private function tituloEdicion(array $partido): string
    {
        return "Editar Partido: {$partido['local_nombre']} vs {$partido['visita_nombre']}";
    }
}