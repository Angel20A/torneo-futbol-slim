<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class IncidenciaRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findAll(array $filters): array
    {
        $sql = <<<'SQL'
SELECT i.*, j.nombres, j.apellidos, e.nombre AS equipo_nombre,
       p.fecha AS partido_fecha, jo.numero_jornada,
       el.nombre AS equipo_local, ev.nombre AS equipo_visita,
       js.numero_jornada AS numero_jornada_suspension
FROM incidencia i
JOIN jugador j ON j.id_jugador = i.id_jugador
JOIN equipo e ON e.id_equipo = j.id_equipo
JOIN partido p ON p.id_partido = i.id_partido
JOIN jornada jo ON jo.id_jornada = p.id_jornada
JOIN equipo el ON el.id_equipo = p.id_equipo_local
JOIN equipo ev ON ev.id_equipo = p.id_equipo_visita
LEFT JOIN jornada js ON js.id_jornada = i.id_jornada_suspension
WHERE 1 = 1
SQL;
        $params = [];
        if (isset($filters['id_equipo'])) {
            $sql .= ' AND j.id_equipo = :id_equipo';
            $params[':id_equipo'] = $filters['id_equipo'];
        }
        if (isset($filters['id_jugador'])) {
            $sql .= ' AND i.id_jugador = :id_jugador';
            $params[':id_jugador'] = $filters['id_jugador'];
        }
        if (isset($filters['tipo_tarjeta'])) {
            $sql .= ' AND i.tipo_tarjeta = :tipo_tarjeta';
            $params[':tipo_tarjeta'] = $filters['tipo_tarjeta'];
        }
        $sql .= ' ORDER BY i.fecha_incidencia DESC, i.id_incidencia DESC';
        $statement = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM incidencia WHERE id_incidencia = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetch() ?: null;
    }

    public function teams(): array
    {
        return $this->db->query('SELECT id_equipo, nombre FROM equipo ORDER BY nombre')->fetchAll();
    }

    public function players(): array
    {
        return $this->db->query(
            'SELECT j.id_jugador, j.id_equipo, j.nombres, j.apellidos, e.nombre AS equipo_nombre
             FROM jugador j JOIN equipo e ON e.id_equipo = j.id_equipo
             ORDER BY e.nombre, j.apellidos, j.nombres'
        )->fetchAll();
    }

    public function matches(): array
    {
        return $this->db->query(
            'SELECT p.id_partido, p.id_jornada, p.id_equipo_local, p.id_equipo_visita, p.fecha,
                    jo.numero_jornada, el.nombre AS equipo_local, ev.nombre AS equipo_visita
             FROM partido p
             JOIN jornada jo ON jo.id_jornada = p.id_jornada
             JOIN equipo el ON el.id_equipo = p.id_equipo_local
             JOIN equipo ev ON ev.id_equipo = p.id_equipo_visita
             ORDER BY p.fecha DESC, p.id_partido DESC'
        )->fetchAll();
    }

    public function jornadas(): array
    {
        return $this->db->query(
            'SELECT id_jornada, numero_jornada, fecha_juego, descripcion
             FROM jornada ORDER BY numero_jornada'
        )->fetchAll();
    }

    public function match(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id_partido, id_jornada, id_equipo_local, id_equipo_visita, fecha
             FROM partido WHERE id_partido = :id'
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetch() ?: null;
    }

    public function player(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT id_jugador, id_equipo FROM jugador WHERE id_jugador = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetch() ?: null;
    }

    public function jornada(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT id_jornada, fecha_juego FROM jornada WHERE id_jornada = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetch() ?: null;
    }

    public function playersForMatch(int $matchId): array
    {
        $statement = $this->db->prepare(
            'SELECT j.id_jugador, j.id_equipo, j.nombres, j.apellidos, e.nombre AS equipo_nombre
             FROM jugador j
             JOIN equipo e ON e.id_equipo = j.id_equipo
             JOIN partido p ON p.id_partido = :id
             WHERE j.id_equipo IN (p.id_equipo_local, p.id_equipo_visita)
             ORDER BY e.nombre, j.apellidos, j.nombres'
        );
        $statement->bindValue(':id', $matchId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function insert(array $data): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO incidencia
             (id_partido, id_jugador, tipo_tarjeta, descripcion, fecha_incidencia,
              fecha_suspension, id_jornada_suspension)
             VALUES (:id_partido, :id_jugador, :tipo_tarjeta, :descripcion, :fecha_incidencia,
                     :fecha_suspension, :id_jornada_suspension)'
        );
        $this->bindFields($statement, $data);
        $statement->execute();
    }

    public function update(int $id, array $data): void
    {
        $statement = $this->db->prepare(
            'UPDATE incidencia SET id_partido = :id_partido, id_jugador = :id_jugador,
             tipo_tarjeta = :tipo_tarjeta, descripcion = :descripcion,
             fecha_incidencia = :fecha_incidencia, fecha_suspension = :fecha_suspension,
             id_jornada_suspension = :id_jornada_suspension WHERE id_incidencia = :id'
        );
        $this->bindFields($statement, $data);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM incidencia WHERE id_incidencia = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    private function bindFields(PDOStatement $statement, array $data): void
    {
        $statement->bindValue(':id_partido', $data['id_partido'], PDO::PARAM_INT);
        $statement->bindValue(':id_jugador', $data['id_jugador'], PDO::PARAM_INT);
        $statement->bindValue(':tipo_tarjeta', $data['tipo_tarjeta'], PDO::PARAM_STR);
        $statement->bindValue(':descripcion', $data['descripcion'], PDO::PARAM_STR);
        $statement->bindValue(':fecha_incidencia', $data['fecha_incidencia'], PDO::PARAM_STR);
        $statement->bindValue(
            ':fecha_suspension',
            $data['fecha_suspension'],
            $data['fecha_suspension'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $statement->bindValue(
            ':id_jornada_suspension',
            $data['id_jornada_suspension'],
            $data['id_jornada_suspension'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
        );
    }
}
