<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\IncidenciaRepository;
use App\Support\Flash;
use App\Support\IncidenciaCsrf;
use App\Validation\IncidenciaValidator;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class IncidenciaController
{
    public function __construct(
        private readonly Twig $view,
        private readonly IncidenciaRepository $repository,
        private readonly IncidenciaValidator $validator,
        private readonly Flash $flash,
        private readonly IncidenciaCsrf $csrf
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $values = [
            'id_equipo' => $this->scalar($query['id_equipo'] ?? ''),
            'id_jugador' => $this->scalar($query['id_jugador'] ?? ''),
            'tipo_tarjeta' => $this->scalar($query['tipo_tarjeta'] ?? ''),
        ];
        $filters = [];
        $errors = [];
        foreach (['id_equipo' => 'equipo', 'id_jugador' => 'jugador'] as $key => $label) {
            if ($values[$key] !== '') {
                $id = IncidenciaValidator::positiveId($values[$key]);
                if ($id === null) {
                    $errors[$key] = 'Selecciona un ' . $label . ' válido.';
                } else {
                    $filters[$key] = $id;
                }
            }
        }
        if ($values['tipo_tarjeta'] !== '') {
            if (!in_array($values['tipo_tarjeta'], ['amarilla', 'roja'], true)) {
                $errors['tipo_tarjeta'] = 'Selecciona una tarjeta válida.';
            } else {
                $filters['tipo_tarjeta'] = $values['tipo_tarjeta'];
            }
        }

        try {
            return $this->view->render($response, 'incidencias/index.twig', [
                'incidencias' => $errors === [] ? $this->repository->findAll($filters) : [],
                'equipos' => $this->repository->teams(),
                'jugadores' => $this->repository->players(),
                'filters' => $values,
                'errors' => $errors,
                'csrf_token' => $this->csrf->token(),
            ])->withStatus($errors === [] ? 200 : 422);
        } catch (PDOException) {
            return $this->databaseError($response);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        try {
            return $this->form($response, [
                'id_partido' => '',
                'id_jugador' => '',
                'tipo_tarjeta' => '',
                'descripcion' => '',
                'fecha_incidencia' => '',
                'fecha_suspension' => '',
                'id_jornada_suspension' => '',
            ], [], null);
        } catch (PDOException) {
            return $this->databaseError($response);
        }
    }

    public function store(Request $request, Response $response): Response
    {
        $input = $this->body($request);
        try {
            $result = $this->validateInput($input);
            if ($result['errors'] !== []) {
                return $this->form($response, $input, $result['errors'], null)->withStatus(422);
            }

            $this->repository->insert($result['data']);
            $this->flash->success('Incidencia registrada.');

            return $this->redirectToIndex($request, $response);
        } catch (PDOException) {
            return $this->databaseError($response);
        }
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = $this->routeId($request, $args);
        try {
            $incidencia = $this->repository->find($id);
            if ($incidencia === null) {
                throw new HttpNotFoundException($request);
            }

            return $this->form($response, $incidencia, [], $id);
        } catch (PDOException) {
            return $this->databaseError($response);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $this->routeId($request, $args);
        $input = $this->body($request);
        try {
            if ($this->repository->find($id) === null) {
                throw new HttpNotFoundException($request);
            }
            $result = $this->validateInput($input);
            if ($result['errors'] !== []) {
                return $this->form($response, $input, $result['errors'], $id)->withStatus(422);
            }

            $this->repository->update($id, $result['data']);
            $this->flash->success('Incidencia actualizada.');

            return $this->redirectToIndex($request, $response);
        } catch (PDOException) {
            return $this->databaseError($response);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $this->routeId($request, $args);
        try {
            if (!$this->repository->delete($id)) {
                throw new HttpNotFoundException($request);
            }
            $this->flash->success('Incidencia eliminada.');

            return $this->redirectToIndex($request, $response);
        } catch (PDOException) {
            $this->flash->error('No se pudo eliminar la incidencia.');

            return $this->redirectToIndex($request, $response);
        }
    }

    public function playersForMatch(Request $request, Response $response, array $args): Response
    {
        $id = $this->routeId($request, $args);
        try {
            if ($this->repository->match($id) === null) {
                throw new HttpNotFoundException($request);
            }
            $json = json_encode($this->repository->playersForMatch($id), JSON_THROW_ON_ERROR);
            $response->getBody()->write($json);

            return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
        } catch (PDOException) {
            $response->getBody()->write('{"error":"No se pudieron cargar los jugadores."}');

            return $response->withStatus(503)->withHeader('Content-Type', 'application/json; charset=UTF-8');
        }
    }

    private function validateInput(array $input): array
    {
        $partidoId = IncidenciaValidator::positiveId($input['id_partido'] ?? null);
        $jugadorId = IncidenciaValidator::positiveId($input['id_jugador'] ?? null);
        $jornadaId = IncidenciaValidator::positiveId($input['id_jornada_suspension'] ?? null);

        return $this->validator->validate(
            $input,
            $partidoId === null ? null : $this->repository->match($partidoId),
            $jugadorId === null ? null : $this->repository->player($jugadorId),
            $jornadaId === null ? null : $this->repository->jornada($jornadaId)
        );
    }

    private function form(Response $response, array $values, array $errors, ?int $id): Response
    {
        $matchId = IncidenciaValidator::positiveId($values['id_partido'] ?? null);

        return $this->view->render($response, 'incidencias/form.twig', [
            'values' => $values,
            'errors' => $errors,
            'id' => $id,
            'partidos' => $this->repository->matches(),
            'jugadores' => $matchId === null ? [] : $this->repository->playersForMatch($matchId),
            'jornadas' => $this->repository->jornadas(),
            'csrf_token' => $this->csrf->token(),
        ]);
    }

    private function routeId(Request $request, array $args): int
    {
        $id = IncidenciaValidator::positiveId($args['id'] ?? null);
        if ($id === null) {
            throw new HttpNotFoundException($request);
        }

        return $id;
    }

    private function body(Request $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    private function scalar(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function redirectToIndex(Request $request, Response $response): Response
    {
        $path = RouteContext::fromRequest($request)->getRouteParser()->urlFor('incidencias.index');

        return $response->withHeader('Location', $path)->withStatus(303);
    }

    private function databaseError(Response $response): Response
    {
        return $this->view->render($response->withStatus(503), 'incidencias/error.twig', [
            'message' => 'No se pudo completar la operación. Inténtalo de nuevo más tarde.',
        ]);
    }
}
