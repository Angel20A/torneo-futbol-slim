<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GolRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Retorna todos los registros de goles con detalles del jugador, club, partido y jornada.
     *
     * @param int|null $idPartido Filtro opcional por partido
     * @param int|null $idJugador Filtro opcional por jugador
     * @return array<array<string, mixed>>
     */
    public function all(?int $idPartido = null, ?int $idJugador = null): array
    {
        $sql = 'SELECT g.id_gol, g.id_jugador, g.id_partido, g.cantidad, g.creado_en,
                       j.nombres AS jugador_nombres, j.apellidos AS jugador_apellidos, j.foto_url AS jugador_foto,
                       ej.id_equipo AS jugador_id_equipo, ej.nombre AS equipo_jugador_nombre, ej.foto_url AS equipo_jugador_foto,
                       p.fecha AS partido_fecha, p.id_equipo_local, p.id_equipo_visita,
                       jor.id_jornada, jor.numero_jornada, jor.descripcion AS jornada_descripcion,
                       eloc.nombre AS equipo_local_nombre, eloc.foto_url AS equipo_local_foto,
                       evis.nombre AS equipo_visita_nombre, evis.foto_url AS equipo_visita_foto
                FROM gol g
                INNER JOIN jugador j ON g.id_jugador = j.id_jugador
                INNER JOIN equipo ej ON j.id_equipo = ej.id_equipo
                INNER JOIN partido p ON g.id_partido = p.id_partido
                INNER JOIN jornada jor ON p.id_jornada = jor.id_jornada
                INNER JOIN equipo eloc ON p.id_equipo_local = eloc.id_equipo
                INNER JOIN equipo evis ON p.id_equipo_visita = evis.id_equipo
                WHERE 1=1';

        $params = [];

        if ($idPartido !== null && $idPartido > 0) {
            $sql .= ' AND g.id_partido = :id_partido';
            $params['id_partido'] = $idPartido;
        }

        if ($idJugador !== null && $idJugador > 0) {
            $sql .= ' AND g.id_jugador = :id_jugador';
            $params['id_jugador'] = $idJugador;
        }

        $sql .= ' ORDER BY jor.numero_jornada DESC, p.fecha DESC, g.cantidad DESC, g.id_gol DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Busca un registro de gol específico por su identificador primario.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT g.id_gol, g.id_jugador, g.id_partido, g.cantidad, g.creado_en,
                       j.nombres AS jugador_nombres, j.apellidos AS jugador_apellidos, j.foto_url AS jugador_foto,
                       ej.id_equipo AS jugador_id_equipo, ej.nombre AS equipo_jugador_nombre, ej.foto_url AS equipo_jugador_foto,
                       p.fecha AS partido_fecha, p.id_equipo_local, p.id_equipo_visita,
                       jor.id_jornada, jor.numero_jornada, jor.descripcion AS jornada_descripcion,
                       eloc.nombre AS equipo_local_nombre, eloc.foto_url AS equipo_local_foto,
                       evis.nombre AS equipo_visita_nombre, evis.foto_url AS equipo_visita_foto
                FROM gol g
                INNER JOIN jugador j ON g.id_jugador = j.id_jugador
                INNER JOIN equipo ej ON j.id_equipo = ej.id_equipo
                INNER JOIN partido p ON g.id_partido = p.id_partido
                INNER JOIN jornada jor ON p.id_jornada = jor.id_jornada
                INNER JOIN equipo eloc ON p.id_equipo_local = eloc.id_equipo
                INNER JOIN equipo evis ON p.id_equipo_visita = evis.id_equipo
                WHERE g.id_gol = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $item = $stmt->fetch();

        return $item ?: null;
    }

    /**
     * Inserta un nuevo registro de anotación para un jugador en un partido.
     */
    public function create(int $idJugador, int $idPartido, int $cantidad): int
    {
        $sql = 'INSERT INTO gol (id_jugador, id_partido, cantidad) VALUES (:id_jugador, :id_partido, :cantidad)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_jugador' => $idJugador,
            'id_partido' => $idPartido,
            'cantidad' => $cantidad,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza la cantidad de goles o la asignación de un registro existente.
     */
    public function update(int $id, int $idJugador, int $idPartido, int $cantidad): bool
    {
        $sql = 'UPDATE gol 
                SET id_jugador = :id_jugador,
                    id_partido = :id_partido,
                    cantidad = :cantidad
                WHERE id_gol = :id';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'id_jugador' => $idJugador,
            'id_partido' => $idPartido,
            'cantidad' => $cantidad,
        ]);
    }

    /**
     * Elimina una anotación por su ID.
     */
    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM gol WHERE id_gol = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Valida si ya existe una anotación registrada para un jugador en un partido específico.
     * Evita violaciones a la restricción UNIQUE(id_jugador, id_partido).
     */
    public function existsJugadorPartido(int $idJugador, int $idPartido, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM gol WHERE id_jugador = :id_jugador AND id_partido = :id_partido';
        $params = [
            'id_jugador' => $idJugador,
            'id_partido' => $idPartido,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id_gol <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene el listado de partidos para poblar selectores con sus jornadas y equipos.
     *
     * @return array<array<string, mixed>>
     */
    public function getPartidos(): array
    {
        $sql = 'SELECT p.id_partido, p.fecha, p.id_equipo_local, p.id_equipo_visita,
                       jor.numero_jornada, jor.descripcion AS jornada_descripcion,
                       eloc.nombre AS equipo_local_nombre, eloc.foto_url AS equipo_local_foto,
                       evis.nombre AS equipo_visita_nombre, evis.foto_url AS equipo_visita_foto
                FROM partido p
                INNER JOIN jornada jor ON p.id_jornada = jor.id_jornada
                INNER JOIN equipo eloc ON p.id_equipo_local = eloc.id_equipo
                INNER JOIN equipo evis ON p.id_equipo_visita = evis.id_equipo
                ORDER BY jor.numero_jornada ASC, p.fecha ASC, p.id_partido ASC';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Obtiene los datos de un partido individual.
     *
     * @return array<string, mixed>|null
     */
    public function getPartidoById(int $idPartido): ?array
    {
        $sql = 'SELECT p.id_partido, p.fecha, p.id_equipo_local, p.id_equipo_visita,
                       jor.numero_jornada, jor.descripcion AS jornada_descripcion,
                       eloc.nombre AS equipo_local_nombre, eloc.foto_url AS equipo_local_foto,
                       evis.nombre AS equipo_visita_nombre, evis.foto_url AS equipo_visita_foto
                FROM partido p
                INNER JOIN jornada jor ON p.id_jornada = jor.id_jornada
                INNER JOIN equipo eloc ON p.id_equipo_local = eloc.id_equipo
                INNER JOIN equipo evis ON p.id_equipo_visita = evis.id_equipo
                WHERE p.id_partido = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idPartido]);

        $partido = $stmt->fetch();

        return $partido ?: null;
    }

    /**
     * Verifica la regla de dominio futbolístico: el jugador debe pertenecer
     * al equipo local o visitante del partido disputado.
     */
    public function isJugadorEnPartido(int $idJugador, int $idPartido): bool
    {
        $sql = 'SELECT COUNT(*) 
                FROM jugador j
                INNER JOIN partido p ON (j.id_equipo = p.id_equipo_local OR j.id_equipo = p.id_equipo_visita)
                WHERE j.id_jugador = :id_jugador AND p.id_partido = :id_partido';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_jugador' => $idJugador,
            'id_partido' => $idPartido,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Retorna los jugadores que pertenecen a los equipos que disputan un partido específico.
     *
     * @return array<array<string, mixed>>
     */
    public function getJugadoresPorPartido(int $idPartido): array
    {
        $sql = 'SELECT j.id_jugador, j.nombres, j.apellidos, j.foto_url,
                       e.id_equipo, e.nombre AS equipo_nombre, e.foto_url AS equipo_foto_url
                FROM jugador j
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                INNER JOIN partido p ON (p.id_equipo_local = e.id_equipo OR p.id_equipo_visita = e.id_equipo)
                WHERE p.id_partido = :id_partido
                ORDER BY e.nombre ASC, j.apellidos ASC, j.nombres ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_partido' => $idPartido]);

        return $stmt->fetchAll();
    }

    /**
     * Retorna todos los jugadores habilitados en el torneo con su club.
     *
     * @return array<array<string, mixed>>
     */
    public function getJugadores(): array
    {
        $sql = 'SELECT j.id_jugador, j.nombres, j.apellidos, j.foto_url,
                       e.id_equipo, e.nombre AS equipo_nombre, e.foto_url AS equipo_foto_url
                FROM jugador j
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                ORDER BY e.nombre ASC, j.apellidos ASC, j.nombres ASC';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Calcula métricas y estadísticas clave para el módulo de goles.
     *
     * @return array<string, mixed>
     */
    public function getEstadisticas(): array
    {
        // 1. Total de goles marcados
        $sqlGoles = 'SELECT COALESCE(SUM(cantidad), 0) FROM gol';
        $totalGoles = (int) $this->db->query($sqlGoles)->fetchColumn();

        // 2. Total de registros individuales
        $sqlRegistros = 'SELECT COUNT(*) FROM gol';
        $totalRegistros = (int) $this->db->query($sqlRegistros)->fetchColumn();

        // 3. Cantidad de partidos con al menos un gol
        $sqlPartidosConGol = 'SELECT COUNT(DISTINCT id_partido) FROM gol';
        $partidosConGol = (int) $this->db->query($sqlPartidosConGol)->fetchColumn();

        // 4. Máximo goleador (Pichichi / Líder de goleo)
        $sqlLider = 'SELECT j.nombres, j.apellidos, j.foto_url AS jugador_foto,
                            e.nombre AS equipo_nombre,
                            SUM(g.cantidad) AS total_goles
                     FROM gol g
                     INNER JOIN jugador j ON g.id_jugador = j.id_jugador
                     INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                     GROUP BY j.id_jugador, j.nombres, j.apellidos, j.foto_url, e.nombre
                     ORDER BY total_goles DESC
                     LIMIT 1';
        $lider = $this->db->query($sqlLider)->fetch();

        return [
            'total_goles' => $totalGoles,
            'total_registros' => $totalRegistros,
            'partidos_con_gol' => $partidosConGol,
            'lider' => $lider ?: null,
        ];
    }
}
