<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ReporteRepository;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

final class ReporteController
{
    public function __construct(
        private readonly Twig $view,
        private readonly ReporteRepository $reportes,
        private readonly Flash $flash
    ) {
    }

    /**
     * Centro general de reportes e informes del torneo.
     * Muestra accesos directos y métricas clave consolidadas.
     */
    public function index(Request $request, Response $response): Response
    {
        $metricas = $this->reportes->getMetricasTorneo();

        return $this->view->render($response, 'reportes/index.twig', [
            'title' => 'Centro de Reportes e Informes',
            'metricas' => $metricas,
        ]);
    }

    /**
     * Reporte 4.a: Lista de equipos y jugadores para el árbitro.
     * Incluye datos de los jugadores, su fotografía y el estado de suspensión
     * para la jornada/fecha seleccionada.
     */
    public function arbitro(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $jornadas = $this->reportes->getJornadas();
        $equipos = $this->reportes->getEquipos();

        // Determinar jornada seleccionada (por defecto la primera disponible)
        $idJornada = !empty($params['id_jornada'])
            ? (int) $params['id_jornada']
            : ($jornadas[0]['id_jornada'] ?? 0);

        $idEquipo = !empty($params['id_equipo']) ? (int) $params['id_equipo'] : null;
        $idPartido = !empty($params['id_partido']) ? (int) $params['id_partido'] : null;

        $jornadaActual = $idJornada > 0 ? $this->reportes->getJornadaById($idJornada) : null;
        $partidosJornada = $idJornada > 0 ? $this->reportes->getPartidosPorJornada($idJornada) : [];

        $jugadores = [];
        if ($idJornada > 0) {
            $jugadores = $this->reportes->getReporteArbitro($idJornada, $idEquipo, $idPartido);
        }

        // Agrupar jugadores por equipo para visualización organizada tipo cédula
        $equiposAgrupados = [];
        $totalSuspendidos = 0;
        $totalHabilitados = 0;

        foreach ($jugadores as $jugador) {
            $nombreEquipo = $jugador['equipo_nombre'] ?? 'Sin Equipo';
            if (!isset($equiposAgrupados[$nombreEquipo])) {
                $equiposAgrupados[$nombreEquipo] = [
                    'id_equipo' => $jugador['id_equipo'],
                    'nombre' => $nombreEquipo,
                    'foto_url' => $jugador['equipo_foto_url'],
                    'jugadores' => [],
                    'suspendidos' => 0,
                    'habilitados' => 0,
                ];
            }

            $equiposAgrupados[$nombreEquipo]['jugadores'][] = $jugador;
            if ($jugador['esta_suspendido']) {
                $equiposAgrupados[$nombreEquipo]['suspendidos']++;
                $totalSuspendidos++;
            } else {
                $equiposAgrupados[$nombreEquipo]['habilitados']++;
                $totalHabilitados++;
            }
        }

        return $this->view->render($response, 'reportes/arbitro.twig', [
            'title' => 'Lista de Jugadores y Equipos para el Árbitro',
            'jornadas' => $jornadas,
            'equipos' => $equipos,
            'partidos_jornada' => $partidosJornada,
            'selected_jornada' => $idJornada,
            'selected_equipo' => $idEquipo,
            'selected_partido' => $idPartido,
            'jornada_actual' => $jornadaActual,
            'equipos_agrupados' => $equiposAgrupados,
            'total_jugadores' => count($jugadores),
            'total_suspendidos' => $totalSuspendidos,
            'total_habilitados' => $totalHabilitados,
        ]);
    }

    /**
     * Reporte 4.b: Reporte de incidencias de jugadores.
     * Reporte por equipo (uno o todos los jugadores) con filtros y tarjetas.
     */
    public function incidencias(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEquipo = !empty($params['id_equipo']) ? (int) $params['id_equipo'] : null;
        $idJugador = !empty($params['id_jugador']) ? (int) $params['id_jugador'] : null;
        $tipoTarjeta = !empty($params['tipo_tarjeta']) ? trim((string) $params['tipo_tarjeta']) : null;

        $equipos = $this->reportes->getEquipos();
        $jugadoresFiltro = $this->reportes->getListadoJugadores($idEquipo);
        $incidencias = $this->reportes->getReporteIncidencias($idEquipo, $idJugador, $tipoTarjeta);
        $resumen = $this->reportes->getResumenIncidencias($idEquipo);

        return $this->view->render($response, 'reportes/incidencias.twig', [
            'title' => 'Reporte de Incidencias y Sanciones Disciplinarias',
            'equipos' => $equipos,
            'jugadores_filtro' => $jugadoresFiltro,
            'incidencias' => $incidencias,
            'resumen' => $resumen,
            'selected_equipo' => $idEquipo,
            'selected_jugador' => $idJugador,
            'selected_tipo' => $tipoTarjeta,
        ]);
    }

    /**
     * Reporte 4.c: Reporte de goleadores.
     * Totaliza los goles anotados por cada jugador y los muestra en orden de mayor a menor.
     * Incluye nombre del jugador, fotografía y nombre del equipo.
     */
    public function goleadores(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEquipo = !empty($params['id_equipo']) ? (int) $params['id_equipo'] : null;

        $equipos = $this->reportes->getEquipos();
        $goleadores = $this->reportes->getReporteGoleadores($idEquipo);

        // Identificar podio de los 3 mejores goleadores
        $top3 = array_slice($goleadores, 0, 3);
        $resto = array_slice($goleadores, 3);

        $totalGolesListado = array_sum(array_column($goleadores, 'total_goles'));

        return $this->view->render($response, 'reportes/goleadores.twig', [
            'title' => 'Tabla de Goleadores (Pichichi)',
            'equipos' => $equipos,
            'goleadores' => $goleadores,
            'top3' => $top3,
            'resto' => $resto,
            'selected_equipo' => $idEquipo,
            'total_goles_listado' => $totalGolesListado,
        ]);
    }

    /**
     * Reporte 4.d: Consulta en pantalla de jugador (selector o búsqueda).
     */
    public function jugador(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idJugador = !empty($params['id_jugador']) ? (int) $params['id_jugador'] : null;
        $idEquipo = !empty($params['id_equipo']) ? (int) $params['id_equipo'] : null;

        $equipos = $this->reportes->getEquipos();
        $jugadoresLista = $this->reportes->getListadoJugadores($idEquipo);

        // Si se seleccionó un jugador, cargar su ficha detallada
        $detalle = null;
        if ($idJugador !== null && $idJugador > 0) {
            $detalle = $this->reportes->getConsultaJugador($idJugador);
            if (!$detalle) {
                $this->flash->error('El jugador seleccionado no fue localizado en el sistema.');
            }
        } elseif (!empty($jugadoresLista)) {
            // Seleccionar por defecto el primer jugador para vista inmediata si no se pasó parámetro
            $primerId = (int) $jugadoresLista[0]['id_jugador'];
            $detalle = $this->reportes->getConsultaJugador($primerId);
            $idJugador = $primerId;
        }

        return $this->view->render($response, 'reportes/jugador.twig', [
            'title' => $detalle ? "Expediente del Jugador: {$detalle['jugador']['nombres']} {$detalle['jugador']['apellidos']}" : 'Consulta de Jugador',
            'equipos' => $equipos,
            'jugadores' => $jugadoresLista,
            'selected_equipo' => $idEquipo,
            'selected_jugador' => $idJugador,
            'detalle' => $detalle,
        ]);
    }

    /**
     * Reporte 4.d: Consulta directa por parámetro de ruta /reportes/jugadores/{id}.
     */
    public function jugadorDetalle(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $detalle = $this->reportes->getConsultaJugador($id);

        if (!$detalle) {
            $this->flash->error('El jugador consultado no existe.');
            return $response->withHeader('Location', '/reportes/jugadores')->withStatus(302);
        }

        $equipos = $this->reportes->getEquipos();
        $jugadoresLista = $this->reportes->getListadoJugadores();

        return $this->view->render($response, 'reportes/jugador.twig', [
            'title' => "Expediente del Jugador: {$detalle['jugador']['nombres']} {$detalle['jugador']['apellidos']}",
            'equipos' => $equipos,
            'jugadores' => $jugadoresLista,
            'selected_equipo' => $detalle['jugador']['id_equipo'],
            'selected_jugador' => $id,
            'detalle' => $detalle,
        ]);
    }
}
