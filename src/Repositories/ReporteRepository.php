<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReporteRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Retorna estadísticas globales del torneo para el dashboard de reportes.
     *
     * @return array<string, mixed>
     */
    public function getMetricasTorneo(): array
    {
        $totalGoles = (int) $this->db->query('SELECT COALESCE(SUM(cantidad), 0) FROM gol')->fetchColumn();
        $totalAmarillas = (int) $this->db->query("SELECT COUNT(*) FROM incidencia WHERE tipo_tarjeta = 'amarilla'")->fetchColumn();
        $totalRojas = (int) $this->db->query("SELECT COUNT(*) FROM incidencia WHERE tipo_tarjeta = 'roja'")->fetchColumn();
        $totalSuspensiones = (int) $this->db->query('SELECT COUNT(*) FROM incidencia WHERE id_jornada_suspension IS NOT NULL OR fecha_suspension IS NOT NULL')->fetchColumn();
        $totalJugadores = (int) $this->db->query('SELECT COUNT(*) FROM jugador')->fetchColumn();
        $totalEquipos = (int) $this->db->query('SELECT COUNT(*) FROM equipo')->fetchColumn();
        $totalPartidos = (int) $this->db->query('SELECT COUNT(*) FROM partido')->fetchColumn();
        $totalJornadas = (int) $this->db->query('SELECT COUNT(*) FROM jornada')->fetchColumn();

        // Goleador actual
        $sqlGoleador = 'SELECT j.id_jugador, j.nombres, j.apellidos, j.foto_url, e.nombre AS equipo_nombre,
                               SUM(g.cantidad) AS total_goles
                        FROM gol g
                        INNER JOIN jugador j ON g.id_jugador = j.id_jugador
                        INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                        GROUP BY j.id_jugador, j.nombres, j.apellidos, j.foto_url, e.nombre
                        ORDER BY total_goles DESC
                        LIMIT 1';
        $goleador = $this->db->query($sqlGoleador)->fetch();

        return [
            'total_goles' => $totalGoles,
            'total_amarillas' => $totalAmarillas,
            'total_rojas' => $totalRojas,
            'total_suspensiones' => $totalSuspensiones,
            'total_jugadores' => $totalJugadores,
            'total_equipos' => $totalEquipos,
            'total_partidos' => $totalPartidos,
            'total_jornadas' => $totalJornadas,
            'lider_goleo' => $goleador ?: null,
        ];
    }

    /**
     * Retorna el catálogo completo de jornadas ordenadas por número.
     *
     * @return array<array<string, mixed>>
     */
    public function getJornadas(): array
    {
        $sql = 'SELECT id_jornada, numero_jornada, fecha_juego, descripcion
                FROM jornada
                ORDER BY numero_jornada ASC';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Retorna una jornada por su ID.
     *
     * @return array<string, mixed>|null
     */
    public function getJornadaById(int $idJornada): ?array
    {
        $sql = 'SELECT id_jornada, numero_jornada, fecha_juego, descripcion
                FROM jornada
                WHERE id_jornada = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idJornada]);

        $res = $stmt->fetch();

        return $res ?: null;
    }

    /**
     * Retorna el catálogo de equipos disponibles.
     *
     * @return array<array<string, mixed>>
     */
    public function getEquipos(): array
    {
        $sql = 'SELECT id_equipo, nombre, foto_url
                FROM equipo
                ORDER BY nombre ASC';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Retorna los partidos programados para una jornada dada.
     *
     * @return array<array<string, mixed>>
     */
    public function getPartidosPorJornada(int $idJornada): array
    {
        $sql = 'SELECT p.id_partido, p.id_jornada, p.id_equipo_local, p.id_equipo_visita, p.fecha,
                       el.nombre AS local_nombre, el.foto_url AS local_foto,
                       ev.nombre AS visita_nombre, ev.foto_url AS visita_foto
                FROM partido p
                INNER JOIN equipo el ON p.id_equipo_local = el.id_equipo
                INNER JOIN equipo ev ON p.id_equipo_visita = ev.id_equipo
                WHERE p.id_jornada = :id_jornada
                ORDER BY p.id_partido ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_jornada' => $idJornada]);

        return $stmt->fetchAll();
    }

    /**
     * Reporte 4.a: Lista de equipos y jugadores para el árbitro.
     * Incluye datos del jugador, fotografía y validación de suspensión para la jornada especificada.
     *
     * @param int $idJornada Identificador de la jornada a jugar
     * @param int|null $idEquipo Filtro opcional por equipo
     * @param int|null $idPartido Filtro opcional por partido específico de la jornada
     * @return array<array<string, mixed>>
     */
    public function getReporteArbitro(int $idJornada, ?int $idEquipo = null, ?int $idPartido = null): array
    {
        // 1. Obtener la fecha de juego de la jornada consultada para cruce exacto
        $jornada = $this->getJornadaById($idJornada);
        $fechaJuego = $jornada['fecha_juego'] ?? '';

        $sql = 'SELECT j.id_jugador, j.id_equipo, j.nombres, j.apellidos, j.fecha_nacimiento, j.foto_url,
                       TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) AS edad,
                       e.nombre AS equipo_nombre, e.foto_url AS equipo_foto_url,
                       CASE WHEN susp.id_incidencia IS NOT NULL THEN 1 ELSE 0 END AS esta_suspendido,
                       susp.tipo_tarjeta AS suspension_tipo_tarjeta,
                       susp.descripcion AS suspension_motivo,
                       susp.fecha_incidencia AS suspension_fecha_origen,
                       susp.fecha_suspension,
                       susp.id_jornada_suspension,
                       susp.partido_origen_jornada,
                       susp.partido_origen_local,
                       susp.partido_origen_visita
                FROM jugador j
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                LEFT JOIN (
                    SELECT i.id_incidencia, i.id_jugador, i.tipo_tarjeta, i.descripcion, i.fecha_incidencia,
                           i.fecha_suspension, i.id_jornada_suspension,
                           jor_orig.numero_jornada AS partido_origen_jornada,
                           el_orig.nombre AS partido_origen_local,
                           ev_orig.nombre AS partido_origen_visita
                    FROM incidencia i
                    INNER JOIN partido p_orig ON p_orig.id_partido = i.id_partido
                    INNER JOIN jornada jor_orig ON jor_orig.id_jornada = p_orig.id_jornada
                    INNER JOIN equipo el_orig ON el_orig.id_equipo = p_orig.id_equipo_local
                    INNER JOIN equipo ev_orig ON ev_orig.id_equipo = p_orig.id_equipo_visita
                    WHERE (
                        i.id_jornada_suspension = :id_jornada_susp
                        OR (i.fecha_suspension IS NOT NULL AND i.fecha_suspension = :fecha_susp)
                    )
                ) susp ON susp.id_jugador = j.id_jugador
                WHERE 1=1';

        $params = [
            'id_jornada_susp' => $idJornada,
            'fecha_susp' => $fechaJuego,
        ];

        // Filtro por partido: solo los jugadores de los 2 equipos del partido
        if ($idPartido !== null && $idPartido > 0) {
            $sql .= ' AND j.id_equipo IN (
                SELECT id_equipo_local FROM partido WHERE id_partido = :partido_1
                UNION
                SELECT id_equipo_visita FROM partido WHERE id_partido = :partido_2
            )';
            $params['partido_1'] = $idPartido;
            $params['partido_2'] = $idPartido;
        } elseif ($idEquipo !== null && $idEquipo > 0) {
            // Filtro por equipo específico
            $sql .= ' AND j.id_equipo = :id_equipo';
            $params['id_equipo'] = $idEquipo;
        } else {
            // Si la jornada tiene partidos definidos, filtramos preferentemente los equipos participantes de la jornada
            $sqlPartidos = 'SELECT COUNT(*) FROM partido WHERE id_jornada = :chk_jornada';
            $stmtChk = $this->db->prepare($sqlPartidos);
            $stmtChk->execute(['chk_jornada' => $idJornada]);
            if ((int) $stmtChk->fetchColumn() > 0) {
                $sql .= ' AND j.id_equipo IN (
                    SELECT id_equipo_local FROM partido WHERE id_jornada = :jornada_eq1
                    UNION
                    SELECT id_equipo_visita FROM partido WHERE id_jornada = :jornada_eq2
                )';
                $params['jornada_eq1'] = $idJornada;
                $params['jornada_eq2'] = $idJornada;
            }
        }

        $sql .= ' ORDER BY e.nombre ASC, j.apellidos ASC, j.nombres ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Reporte 4.b: Reporte de incidencias de jugadores.
     * Permite filtrar por equipo (uno o todos) y opcionalmente por jugador o tipo de tarjeta.
     *
     * @param int|null $idEquipo
     * @param int|null $idJugador
     * @param string|null $tipoTarjeta
     * @return array<array<string, mixed>>
     */
    public function getReporteIncidencias(
        ?int $idEquipo = null,
        ?int $idJugador = null,
        ?string $tipoTarjeta = null
    ): array {
        $sql = 'SELECT i.id_incidencia, i.id_jugador, i.id_partido, i.tipo_tarjeta, i.descripcion,
                       i.fecha_incidencia, i.fecha_suspension, i.id_jornada_suspension, i.creado_en,
                       j.nombres AS jugador_nombres, j.apellidos AS jugador_apellidos, j.foto_url AS jugador_foto,
                       e.id_equipo, e.nombre AS equipo_nombre, e.foto_url AS equipo_foto,
                       p.fecha AS partido_fecha,
                       jor.id_jornada, jor.numero_jornada, jor.descripcion AS jornada_descripcion,
                       el.nombre AS equipo_local_nombre, el.foto_url AS equipo_local_foto,
                       ev.nombre AS equipo_visita_nombre, ev.foto_url AS equipo_visita_foto,
                       js.numero_jornada AS numero_jornada_suspension,
                       js.fecha_juego AS fecha_juego_suspension,
                       js.descripcion AS jornada_suspension_desc
                FROM incidencia i
                INNER JOIN jugador j ON i.id_jugador = j.id_jugador
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                INNER JOIN partido p ON i.id_partido = p.id_partido
                INNER JOIN jornada jor ON p.id_jornada = jor.id_jornada
                INNER JOIN equipo el ON p.id_equipo_local = el.id_equipo
                INNER JOIN equipo ev ON p.id_equipo_visita = ev.id_equipo
                LEFT JOIN jornada js ON i.id_jornada_suspension = js.id_jornada
                WHERE 1=1';

        $params = [];

        if ($idEquipo !== null && $idEquipo > 0) {
            $sql .= ' AND j.id_equipo = :id_equipo';
            $params['id_equipo'] = $idEquipo;
        }

        if ($idJugador !== null && $idJugador > 0) {
            $sql .= ' AND i.id_jugador = :id_jugador';
            $params['id_jugador'] = $idJugador;
        }

        if (!empty($tipoTarjeta) && in_array($tipoTarjeta, ['amarilla', 'roja'], true)) {
            $sql .= ' AND i.tipo_tarjeta = :tipo_tarjeta';
            $params['tipo_tarjeta'] = $tipoTarjeta;
        }

        $sql .= ' ORDER BY i.fecha_incidencia DESC, i.id_incidencia DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Métricas resumidas de incidencias para cabecera de reporte 4.b
     *
     * @param int|null $idEquipo
     * @return array<string, int>
     */
    public function getResumenIncidencias(?int $idEquipo = null): array
    {
        $sql = 'SELECT 
                    COUNT(*) AS total_incidencias,
                    SUM(CASE WHEN i.tipo_tarjeta = "amarilla" THEN 1 ELSE 0 END) AS total_amarillas,
                    SUM(CASE WHEN i.tipo_tarjeta = "roja" THEN 1 ELSE 0 END) AS total_rojas,
                    SUM(CASE WHEN i.id_jornada_suspension IS NOT NULL OR i.fecha_suspension IS NOT NULL THEN 1 ELSE 0 END) AS total_suspensiones
                FROM incidencia i
                INNER JOIN jugador j ON i.id_jugador = j.id_jugador
                WHERE 1=1';

        $params = [];
        if ($idEquipo !== null && $idEquipo > 0) {
            $sql .= ' AND j.id_equipo = :id_equipo';
            $params['id_equipo'] = $idEquipo;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch() ?: [];

        return [
            'total_incidencias' => (int) ($row['total_incidencias'] ?? 0),
            'total_amarillas' => (int) ($row['total_amarillas'] ?? 0),
            'total_rojas' => (int) ($row['total_rojas'] ?? 0),
            'total_suspensiones' => (int) ($row['total_suspensiones'] ?? 0),
        ];
    }

    /**
     * Reporte 4.c: Reporte de goleadores.
     * Totaliza los goles anotados por cada jugador y los muestra en orden de mayor a menor.
     * Incluye nombre del jugador, fotografía y nombre del equipo.
     *
     * @param int|null $idEquipo Filtro opcional por equipo
     * @return array<array<string, mixed>>
     */
    public function getReporteGoleadores(?int $idEquipo = null): array
    {
        $sql = 'SELECT j.id_jugador, j.nombres, j.apellidos, j.foto_url AS jugador_foto,
                       TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) AS edad,
                       e.id_equipo, e.nombre AS equipo_nombre, e.foto_url AS equipo_foto,
                       SUM(g.cantidad) AS total_goles,
                       COUNT(DISTINCT g.id_partido) AS partidos_con_gol,
                       MAX(p.fecha) AS ultima_fecha_gol
                FROM gol g
                INNER JOIN jugador j ON g.id_jugador = j.id_jugador
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                INNER JOIN partido p ON g.id_partido = p.id_partido
                WHERE 1=1';

        $params = [];

        if ($idEquipo !== null && $idEquipo > 0) {
            $sql .= ' AND j.id_equipo = :id_equipo';
            $params['id_equipo'] = $idEquipo;
        }

        $sql .= ' GROUP BY j.id_jugador, j.nombres, j.apellidos, j.foto_url, j.fecha_nacimiento,
                           e.id_equipo, e.nombre, e.foto_url
                  ORDER BY total_goles DESC, partidos_con_gol DESC, j.apellidos ASC, j.nombres ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        // Asignar posición en el ranking (1, 2, 3...)
        $posicion = 1;
        $resultado = [];
        foreach ($rows as $row) {
            $row['posicion'] = $posicion++;
            $row['total_goles'] = (int) $row['total_goles'];
            $row['partidos_con_gol'] = (int) $row['partidos_con_gol'];
            $resultado[] = $row;
        }

        return $resultado;
    }

    /**
     * Reporte 4.d: Consulta en pantalla de jugador.
     * Muestra toda la información del jugador indicado:
     * - Datos personales completos y equipo
     * - Estadísticas acumuladas (goles, tarjetas, estado de suspensión)
     * - Historial detallado de goles partido a partido
     * - Historial detallado de incidencias y suspensiones
     *
     * @param int $idJugador
     * @return array<string, mixed>|null
     */
    public function getConsultaJugador(int $idJugador): ?array
    {
        // 1. Datos básicos del jugador y su equipo
        $sql = 'SELECT j.id_jugador, j.id_equipo, j.nombres, j.apellidos, j.fecha_nacimiento,
                       j.foto_url, j.creado_en,
                       TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) AS edad,
                       e.nombre AS equipo_nombre, e.foto_url AS equipo_foto_url
                FROM jugador j
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                WHERE j.id_jugador = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idJugador]);

        $jugador = $stmt->fetch();
        if (!$jugador) {
            return null;
        }

        // 2. Total de goles
        $sqlGolesTotales = 'SELECT COALESCE(SUM(cantidad), 0) FROM gol WHERE id_jugador = :id';
        $stmtGoles = $this->db->prepare($sqlGolesTotales);
        $stmtGoles->execute(['id' => $idJugador]);
        $totalGoles = (int) $stmtGoles->fetchColumn();

        // 3. Desglose de goles partido por partido
        $sqlHistorialGoles = 'SELECT g.id_gol, g.cantidad, g.creado_en,
                                     p.id_partido, p.fecha AS partido_fecha,
                                     jor.numero_jornada, jor.descripcion AS jornada_descripcion,
                                     el.nombre AS local_nombre, el.foto_url AS local_foto,
                                     ev.nombre AS visita_nombre, ev.foto_url AS visita_foto
                              FROM gol g
                              INNER JOIN partido p ON g.id_partido = p.id_partido
                              INNER JOIN jornada jor ON p.id_jornada = jor.id_jornada
                              INNER JOIN equipo el ON p.id_equipo_local = el.id_equipo
                              INNER JOIN equipo ev ON p.id_equipo_visita = ev.id_equipo
                              WHERE g.id_jugador = :id
                              ORDER BY jor.numero_jornada ASC, p.fecha ASC';

        $stmtHistorialGoles = $this->db->prepare($sqlHistorialGoles);
        $stmtHistorialGoles->execute(['id' => $idJugador]);
        $historialGoles = $stmtHistorialGoles->fetchAll();

        // 4. Historial de incidencias
        $sqlHistorialIncidencias = 'SELECT i.id_incidencia, i.tipo_tarjeta, i.descripcion,
                                           i.fecha_incidencia, i.fecha_suspension, i.id_jornada_suspension,
                                           p.id_partido, p.fecha AS partido_fecha,
                                           jor.numero_jornada, jor.descripcion AS jornada_descripcion,
                                           el.nombre AS local_nombre, el.foto_url AS local_foto,
                                           ev.nombre AS visita_nombre, ev.foto_url AS visita_foto,
                                           js.numero_jornada AS jornada_suspension_numero,
                                           js.fecha_juego AS jornada_suspension_fecha
                                    FROM incidencia i
                                    INNER JOIN partido p ON i.id_partido = p.id_partido
                                    INNER JOIN jornada jor ON p.id_jornada = jor.id_jornada
                                    INNER JOIN equipo el ON p.id_equipo_local = el.id_equipo
                                    INNER JOIN equipo ev ON p.id_equipo_visita = ev.id_equipo
                                    LEFT JOIN jornada js ON i.id_jornada_suspension = js.id_jornada
                                    WHERE i.id_jugador = :id
                                    ORDER BY i.fecha_incidencia DESC';

        $stmtHistorialInc = $this->db->prepare($sqlHistorialIncidencias);
        $stmtHistorialInc->execute(['id' => $idJugador]);
        $historialIncidencias = $stmtHistorialInc->fetchAll();

        // 5. Conteo de amarillas, rojas y suspensiones
        $totalAmarillas = 0;
        $totalRojas = 0;
        $suspensionesActivas = [];

        foreach ($historialIncidencias as $inc) {
            if ($inc['tipo_tarjeta'] === 'amarilla') {
                $totalAmarillas++;
            } elseif ($inc['tipo_tarjeta'] === 'roja') {
                $totalRojas++;
            }

            if (!empty($inc['id_jornada_suspension']) || !empty($inc['fecha_suspension'])) {
                $suspensionesActivas[] = $inc;
            }
        }

        return [
            'jugador' => $jugador,
            'total_goles' => $totalGoles,
            'total_amarillas' => $totalAmarillas,
            'total_rojas' => $totalRojas,
            'historial_goles' => $historialGoles,
            'historial_incidencias' => $historialIncidencias,
            'suspensiones' => $suspensionesActivas,
            'tiene_suspension' => !empty($suspensionesActivas),
        ];
    }

    /**
     * Retorna todos los jugadores formateados para listas desplegables.
     *
     * @param int|null $idEquipo
     * @return array<array<string, mixed>>
     */
    public function getListadoJugadores(?int $idEquipo = null): array
    {
        $sql = 'SELECT j.id_jugador, j.id_equipo, j.nombres, j.apellidos, j.foto_url,
                       e.nombre AS equipo_nombre
                FROM jugador j
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                WHERE 1=1';

        $params = [];
        if ($idEquipo !== null && $idEquipo > 0) {
            $sql .= ' AND j.id_equipo = :id_equipo';
            $params['id_equipo'] = $idEquipo;
        }

        $sql .= ' ORDER BY e.nombre ASC, j.apellidos ASC, j.nombres ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
