<?php

declare(strict_types=1);

namespace App\Validation;

final class IncidenciaValidator
{
    public static function positiveId(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (!preg_match('/^[1-9][0-9]*$/D', $value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    public function validate(array $input, ?array $partido, ?array $jugador, ?array $jornada): array
    {
        $partidoId = self::positiveId($input['id_partido'] ?? null);
        $jugadorId = self::positiveId($input['id_jugador'] ?? null);
        $tipo = $this->value($input, 'tipo_tarjeta');
        $descripcion = $this->value($input, 'descripcion');
        $fecha = $this->value($input, 'fecha_incidencia');
        $fechaSuspension = $this->value($input, 'fecha_suspension');
        $jornadaId = self::positiveId($input['id_jornada_suspension'] ?? null);
        $errors = [];

        if ($partidoId === null || $partido === null) {
            $errors['id_partido'] = 'Selecciona un partido válido.';
        }
        if ($jugadorId === null || $jugador === null) {
            $errors['id_jugador'] = 'Selecciona un jugador válido.';
        } elseif ($partido !== null && (int) $jugador['id_equipo'] !== (int) $partido['id_equipo_local']
            && (int) $jugador['id_equipo'] !== (int) $partido['id_equipo_visita']) {
            $errors['id_jugador'] = 'El jugador no pertenece a los equipos de ese partido.';
        }

        if (!in_array($tipo, ['amarilla', 'roja'], true)) {
            $errors['tipo_tarjeta'] = 'Selecciona una tarjeta amarilla o roja.';
        }
        if ($descripcion === '') {
            $errors['descripcion'] = 'Escribe una descripción.';
        }
        if (!$this->validDate($fecha)) {
            $errors['fecha_incidencia'] = 'Introduce una fecha real con formato YYYY-MM-DD.';
        }

        if ($tipo === 'roja') {
            if (!$this->validDate($fechaSuspension)) {
                $errors['fecha_suspension'] = 'La tarjeta roja exige una fecha real de suspensión (YYYY-MM-DD).';
            } elseif (!isset($errors['fecha_incidencia']) && $fechaSuspension < $fecha) {
                $errors['fecha_suspension'] = 'La suspensión no puede ser anterior a la incidencia.';
            }

            if ($this->value($input, 'id_jornada_suspension') !== '') {
                if ($jornadaId === null || $jornada === null) {
                    $errors['id_jornada_suspension'] = 'Selecciona una jornada válida.';
                } elseif (!isset($errors['fecha_suspension'])
                    && $fechaSuspension !== $jornada['fecha_juego']) {
                    $errors['fecha_suspension'] = 'La fecha debe coincidir con la jornada de suspensión.';
                }
            }
        } else {
            $fechaSuspension = null;
            $jornadaId = null;
        }

        return [
            'data' => [
                'id_partido' => $partidoId,
                'id_jugador' => $jugadorId,
                'tipo_tarjeta' => $tipo,
                'descripcion' => $descripcion,
                'fecha_incidencia' => $fecha,
                'fecha_suspension' => $fechaSuspension,
                'id_jornada_suspension' => $jornadaId,
            ],
            'errors' => $errors,
        ];
    }

    private function value(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function validDate(string $value): bool
    {
        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
