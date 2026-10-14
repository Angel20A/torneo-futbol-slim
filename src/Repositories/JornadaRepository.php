<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class JornadaRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Retorna todas las jornadas con el conteo de partidos y de suspensiones asignadas
     *
     * @return array<array<string, mixed>>
     */
    public function all(): array
    {
        $sql = 'SELECT j.id_jornada, j.numero_jornada, j.fecha_juego, j.descripcion,
                       (SELECT COUNT(*) FROM partido p WHERE p.id_jornada = j.id_jornada) AS total_partidos,
                       (SELECT COUNT(*) FROM incidencia i WHERE i.id_jornada_suspension = j.id_jornada) AS total_suspensiones
                FROM jornada j
                ORDER BY j.numero_jornada ASC';

        $stmt = $this->db->query($sql);

        return array_map(fn (array $row): array => $this->normalize($row), $stmt->fetchAll());
    }

    /**
     * Busca una jornada por su ID (incluye conteo de partidos y suspensiones)
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT j.id_jornada, j.numero_jornada, j.fecha_juego, j.descripcion,
                       (SELECT COUNT(*) FROM partido p WHERE p.id_jornada = j.id_jornada) AS total_partidos,
                       (SELECT COUNT(*) FROM incidencia i WHERE i.id_jornada_suspension = j.id_jornada) AS total_suspensiones
                FROM jornada j
                WHERE j.id_jornada = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $jornada = $stmt->fetch();

        return $jornada ? $this->normalize($jornada) : null;
    }

    /**
     * Inserta una nueva jornada y retorna el ID autogenerado
     */
    public function create(int $numero, string $fecha, ?string $descripcion): int
    {
        $sql = 'INSERT INTO jornada (numero_jornada, fecha_juego, descripcion)
                VALUES (:numero, :fecha, :descripcion)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'numero' => $numero,
            'fecha' => $fecha,
            'descripcion' => $descripcion,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza una jornada. Si cambia la fecha, sincroniza en la misma transacción la fecha
     * de sus partidos y de las suspensiones asignadas a ella.
     *
     * @return int Cantidad de partidos cuya fecha fue actualizada
     */
    public function update(int $id, int $numero, string $fecha, ?string $descripcion): int
    {
        $actual = $this->findById($id);
        $cambioFecha = $actual !== null && (string) $actual['fecha_juego'] !== $fecha;

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'UPDATE jornada
                 SET numero_jornada = :numero, fecha_juego = :fecha, descripcion = :descripcion
                 WHERE id_jornada = :id'
            );
            $stmt->execute([
                'id' => $id,
                'numero' => $numero,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
            ]);

            $partidosActualizados = 0;

            if ($cambioFecha) {
                $stmt = $this->db->prepare('UPDATE partido SET fecha = :fecha WHERE id_jornada = :id');
                $stmt->execute(['id' => $id, 'fecha' => $fecha]);
                $partidosActualizados = $stmt->rowCount();

                $stmt = $this->db->prepare(
                    'UPDATE incidencia SET fecha_suspension = :fecha
                     WHERE id_jornada_suspension = :id AND fecha_suspension IS NOT NULL'
                );
                $stmt->execute(['id' => $id, 'fecha' => $fecha]);
            }

            $this->db->commit();

            return $partidosActualizados;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Elimina una jornada por ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM jornada WHERE id_jornada = :id');

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verifica si ya existe otra jornada con el mismo número (evita violación de UNIQUE)
     */
    public function existsNumero(int $numero, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM jornada WHERE numero_jornada = :numero';
        $params = ['numero' => $numero];

        if ($excluirId !== null) {
            $sql .= ' AND id_jornada <> :excluir';
            $params['excluir'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Número sugerido para la próxima jornada (máximo actual + 1)
     */
    public function siguienteNumero(): int
    {
        return (int) $this->db->query('SELECT COALESCE(MAX(numero_jornada), 0) + 1 FROM jornada')->fetchColumn();
    }

    /**
     * Jornada inmediatamente anterior a un número dado (la de mayor número menor al indicado)
     *
     * @return array<string, mixed>|null
     */
    public function anteriorA(int $numero, ?int $excluirId = null): ?array
    {
        return $this->vecina('<', 'DESC', $numero, $excluirId);
    }

    /**
     * Jornada inmediatamente posterior a un número dado (la de menor número mayor al indicado)
     *
     * @return array<string, mixed>|null
     */
    public function siguienteA(int $numero, ?int $excluirId = null): ?array
    {
        return $this->vecina('>', 'ASC', $numero, $excluirId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vecina(string $operador, string $orden, int $numero, ?int $excluirId): ?array
    {
        $sql = "SELECT id_jornada, numero_jornada, fecha_juego
                FROM jornada
                WHERE numero_jornada {$operador} :numero";
        $params = ['numero' => $numero];

        if ($excluirId !== null) {
            $sql .= ' AND id_jornada <> :excluir';
            $params['excluir'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql . " ORDER BY numero_jornada {$orden} LIMIT 1");
        $stmt->execute($params);

        $jornada = $stmt->fetch();

        return $jornada ?: null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $row['total_partidos'] = (int) $row['total_partidos'];
        $row['total_suspensiones'] = (int) $row['total_suspensiones'];

        return $row;
    }
}