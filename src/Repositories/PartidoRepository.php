<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PartidoRepository
{
    /**
     * Consulta base de partidos: une jornada y equipos, y calcula el marcador
     * a partir de los goles registrados (cada gol pertenece al equipo de su jugador).
     */
    private const SELECT_BASE = 'SELECT p.id_partido, p.id_jornada, p.id_equipo_local, p.id_equipo_visita,
                p.fecha, p.creado_en,
                j.numero_jornada, j.descripcion AS jornada_descripcion,
                el.nombre AS local_nombre, el.foto_url AS local_foto_url,
                ev.nombre AS visita_nombre, ev.foto_url AS visita_foto_url,
                (SELECT COALESCE(SUM(g.cantidad), 0)
                   FROM gol g
                   INNER JOIN jugador jg ON jg.id_jugador = g.id_jugador
                  WHERE g.id_partido = p.id_partido AND jg.id_equipo = p.id_equipo_local) AS goles_local,
                (SELECT COALESCE(SUM(g.cantidad), 0)
                   FROM gol g
                   INNER JOIN jugador jg ON jg.id_jugador = g.id_jugador
                  WHERE g.id_partido = p.id_partido AND jg.id_equipo = p.id_equipo_visita) AS goles_visita,
                (SELECT COUNT(*) FROM gol g WHERE g.id_partido = p.id_partido) AS total_registros_gol,
                (SELECT COUNT(*) FROM incidencia i WHERE i.id_partido = p.id_partido) AS total_incidencias
            FROM partido p
            INNER JOIN jornada j ON j.id_jornada = p.id_jornada
            INNER JOIN equipo el ON el.id_equipo = p.id_equipo_local
            INNER JOIN equipo ev ON ev.id_equipo = p.id_equipo_visita';

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Retorna los partidos con filtros opcionales por jornada y/o equipo (local o visita)
     *
     * @return array<array<string, mixed>>
     */
    public function all(?int $idJornada = null, ?int $idEquipo = null): array
    {
        $sql = self::SELECT_BASE . ' WHERE 1=1';
        $params = [];

        if ($idJornada !== null && $idJornada > 0) {
            $sql .= ' AND p.id_jornada = :id_jornada';
            $params['id_jornada'] = $idJornada;
        }

        if ($idEquipo !== null && $idEquipo > 0) {
            $sql .= ' AND (p.id_equipo_local = :equipo_local OR p.id_equipo_visita = :equipo_visita)';
            $params['equipo_local'] = $idEquipo;
            $params['equipo_visita'] = $idEquipo;
        }

        $sql .= ' ORDER BY j.numero_jornada ASC, p.id_partido ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->normalize($row), $stmt->fetchAll());
    }

    /**
     * Busca un partido por ID
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT_BASE . ' WHERE p.id_partido = :id');
        $stmt->execute(['id' => $id]);

        $partido = $stmt->fetch();

        return $partido ? $this->normalize($partido) : null;
    }

    /**
     * Inserta un nuevo partido y retorna el ID autogenerado
     */
    public function create(int $idJornada, int $idLocal, int $idVisita, string $fecha): int
    {
        $sql = 'INSERT INTO partido (id_jornada, id_equipo_local, id_equipo_visita, fecha)
                VALUES (:id_jornada, :id_local, :id_visita, :fecha)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_jornada' => $idJornada,
            'id_local' => $idLocal,
            'id_visita' => $idVisita,
            'fecha' => $fecha,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza jornada, equipos y fecha de un partido
     */
    public function update(int $id, int $idJornada, int $idLocal, int $idVisita, string $fecha): bool
    {
        $sql = 'UPDATE partido
                SET id_jornada = :id_jornada,
                    id_equipo_local = :id_local,
                    id_equipo_visita = :id_visita,
                    fecha = :fecha
                WHERE id_partido = :id';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'id_jornada' => $idJornada,
            'id_local' => $idLocal,
            'id_visita' => $idVisita,
            'fecha' => $fecha,
        ]);
    }

    /**
     * Elimina un partido por ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM partido WHERE id_partido = :id');

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Retorna el ID del partido en que un equipo ya juega dentro de una jornada (o null si está libre)
     */
    public function partidoDeEquipoEnJornada(int $idJornada, int $idEquipo, ?int $excluirId = null): ?int
    {
        $sql = 'SELECT id_partido FROM partido
                WHERE id_jornada = :id_jornada
                  AND (id_equipo_local = :equipo_local OR id_equipo_visita = :equipo_visita)';

        $params = [
            'id_jornada' => $idJornada,
            'equipo_local' => $idEquipo,
            'equipo_visita' => $idEquipo,
        ];

        if ($excluirId !== null) {
            $sql .= ' AND id_partido <> :excluir';
            $params['excluir'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Mapa [id_jornada => [ids de equipos que ya juegan en esa jornada]].
     * Se usa en el formulario para deshabilitar equipos ocupados antes de enviar.
     *
     * @return array<int, list<int>>
     */
    public function ocupadosPorJornada(?int $excluirId = null): array
    {
        $rows = $this->db->query(
            'SELECT id_partido, id_jornada, id_equipo_local, id_equipo_visita FROM partido'
        )->fetchAll();

        $mapa = [];
        foreach ($rows as $row) {
            if ($excluirId !== null && (int) $row['id_partido'] === $excluirId) {
                continue;
            }

            $jornada = (int) $row['id_jornada'];
            $mapa[$jornada][] = (int) $row['id_equipo_local'];
            $mapa[$jornada][] = (int) $row['id_equipo_visita'];
        }

        return $mapa;
    }

    /**
     * Números de jornada en las que estos dos equipos ya se enfrentan (en cualquier orden)
     *
     * @return list<int>
     */
    public function jornadasDeEnfrentamiento(int $idA, int $idB, ?int $excluirId = null): array
    {
        $sql = 'SELECT j.numero_jornada
                FROM partido p
                INNER JOIN jornada j ON j.id_jornada = p.id_jornada
                WHERE ((p.id_equipo_local = :a_local AND p.id_equipo_visita = :b_visita)
                    OR (p.id_equipo_local = :b_local AND p.id_equipo_visita = :a_visita))';

        $params = [
            'a_local' => $idA,
            'b_visita' => $idB,
            'b_local' => $idB,
            'a_visita' => $idA,
        ];

        if ($excluirId !== null) {
            $sql .= ' AND p.id_partido <> :excluir';
            $params['excluir'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY j.numero_jornada ASC');
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Plantel de un equipo para un partido: goles anotados en él y si el jugador
     * está suspendido (tarjeta roja con suspensión para esa jornada o fecha).
     *
     * @return array<array<string, mixed>>
     */
    public function plantel(int $idEquipo, int $idJornada, string $fecha, int $idPartido): array
    {
        $sql = "SELECT j.id_jugador, j.nombres, j.apellidos, j.foto_url,
                       TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) AS edad,
                       (SELECT COALESCE(SUM(g.cantidad), 0)
                          FROM gol g
                         WHERE g.id_jugador = j.id_jugador AND g.id_partido = :id_partido) AS goles,
                       EXISTS (SELECT 1 FROM incidencia i
                                WHERE i.id_jugador = j.id_jugador
                                  AND i.tipo_tarjeta = 'roja'
                                  AND (i.id_jornada_suspension = :id_jornada OR i.fecha_suspension = :fecha)) AS suspendido
                FROM jugador j
                WHERE j.id_equipo = :id_equipo
                ORDER BY j.apellidos ASC, j.nombres ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_partido' => $idPartido,
            'id_jornada' => $idJornada,
            'fecha' => $fecha,
            'id_equipo' => $idEquipo,
        ]);

        return array_map(static function (array $row): array {
            $row['edad'] = (int) $row['edad'];
            $row['goles'] = (int) $row['goles'];
            $row['suspendido'] = (bool) $row['suspendido'];

            return $row;
        }, $stmt->fetchAll());
    }

    /**
     * Tarjetas registradas en un partido
     *
     * @return array<array<string, mixed>>
     */
    public function incidenciasDePartido(int $idPartido): array
    {
        $sql = 'SELECT i.id_incidencia, i.tipo_tarjeta, i.descripcion, i.fecha_incidencia, i.fecha_suspension,
                       j.nombres, j.apellidos, e.nombre AS equipo_nombre
                FROM incidencia i
                INNER JOIN jugador j ON j.id_jugador = i.id_jugador
                INNER JOIN equipo e ON e.id_equipo = j.id_equipo
                WHERE i.id_partido = :id_partido
                ORDER BY i.id_incidencia ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_partido' => $idPartido]);

        return $stmt->fetchAll();
    }

    /**
     * Normaliza a enteros los campos numéricos calculados por la consulta
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        foreach (['goles_local', 'goles_visita', 'total_registros_gol', 'total_incidencias'] as $campo) {
            $row[$campo] = (int) $row[$campo];
        }

        return $row;
    }
}