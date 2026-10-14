<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Middleware\IncidenciaCsrfMiddleware;
use App\Middleware\IncidenciaErrorMiddleware;
use App\Middleware\SessionMiddleware;
use App\Repositories\IncidenciaRepository;
use App\Support\Flash;
use App\Support\IncidenciaCsrf;
use App\Validation\IncidenciaValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$validator = new IncidenciaValidator();
$partido = ['id_equipo_local' => 1, 'id_equipo_visita' => 2];
$jugador = ['id_equipo' => 2];
$jornada = ['fecha_juego' => '2026-10-10'];
$red = [
    'id_partido' => '1',
    'id_jugador' => '2',
    'tipo_tarjeta' => 'roja',
    'descripcion' => ' Falta grave ',
    'fecha_incidencia' => '2026-10-03',
    'fecha_suspension' => '2026-10-10',
    'id_jornada_suspension' => '2',
];

$result = $validator->validate($red, $partido, $jugador, $jornada);
check($result['errors'] === [], 'Una roja válida fue rechazada.');
check($result['data']['descripcion'] === 'Falta grave', 'La descripción no fue normalizada.');

$yellow = $red;
$yellow['tipo_tarjeta'] = 'amarilla';
$result = $validator->validate($yellow, $partido, $jugador, $jornada);
check($result['errors'] === [], 'La conversión a amarilla fue rechazada.');
check($result['data']['fecha_suspension'] === null && $result['data']['id_jornada_suspension'] === null,
    'La amarilla conservó una suspensión.');

$bad = $red;
$bad['fecha_incidencia'] = '2026-02-30';
check(isset($validator->validate($bad, $partido, $jugador, $jornada)['errors']['fecha_incidencia']),
    'Se aceptó una fecha imposible.');
$bad['fecha_incidencia'] = '0000-10-03';
check(isset($validator->validate($bad, $partido, $jugador, $jornada)['errors']['fecha_incidencia']),
    'Se aceptó el año cero.');
$bad = $red;
$bad['fecha_suspension'] = '';
check(isset($validator->validate($bad, $partido, $jugador, $jornada)['errors']['fecha_suspension']),
    'Se aceptó una roja sin suspensión.');
$bad = $red;
$bad['fecha_suspension'] = '2026-10-09';
check(isset($validator->validate($bad, $partido, $jugador, $jornada)['errors']['fecha_suspension']),
    'Se aceptó una jornada y fecha de suspensión discrepantes.');
$bad = $red;
$bad['fecha_suspension'] = '2026-10-02';
check(isset($validator->validate($bad, $partido, $jugador, $jornada)['errors']['fecha_suspension']),
    'Se aceptó una suspensión anterior a la incidencia.');
$bad = $red;
$bad['fecha_suspension'] = '2026-10-03';
$bad['id_jornada_suspension'] = '';
check($validator->validate($bad, $partido, $jugador, null)['errors'] === [],
    'Se rechazó una suspensión el mismo día.');
$bad = $red;
$bad['tipo_tarjeta'] = 'azul';
check(isset($validator->validate($bad, $partido, $jugador, $jornada)['errors']['tipo_tarjeta']),
    'Se aceptó una tarjeta no permitida.');
$bad = $red;
$bad['descripcion'] = "  \t ";
check(isset($validator->validate($bad, $partido, $jugador, $jornada)['errors']['descripcion']),
    'Se aceptó una descripción vacía.');
check(isset($validator->validate($red, $partido, ['id_equipo' => 3], $jornada)['errors']['id_jugador']),
    'Se aceptó un jugador ajeno al partido.');
check(isset($validator->validate($red, null, $jugador, $jornada)['errors']['id_partido']),
    'Se aceptó un partido inexistente.');
check(isset($validator->validate($red, $partido, null, $jornada)['errors']['id_jugador']),
    'Se aceptó un jugador inexistente.');
$bad = $red;
$bad['id_jornada_suspension'] = '999';
check(isset($validator->validate($bad, $partido, $jugador, null)['errors']['id_jornada_suspension']),
    'Se aceptó una jornada inexistente.');
check(IncidenciaValidator::positiveId('0') === null && IncidenciaValidator::positiveId('1 OR 1=1') === null,
    'Se aceptó un identificador inválido.');

// Toda la persistencia de esta prueba ocurre en SQLite en memoria.
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE equipo (id_equipo INTEGER PRIMARY KEY, nombre TEXT NOT NULL)');
$db->exec('CREATE TABLE jornada (id_jornada INTEGER PRIMARY KEY, numero_jornada INTEGER, fecha_juego TEXT, descripcion TEXT)');
$db->exec('CREATE TABLE jugador (id_jugador INTEGER PRIMARY KEY, id_equipo INTEGER, nombres TEXT, apellidos TEXT)');
$db->exec('CREATE TABLE partido (id_partido INTEGER PRIMARY KEY, id_jornada INTEGER, id_equipo_local INTEGER, id_equipo_visita INTEGER, fecha TEXT)');
$db->exec('CREATE TABLE incidencia (id_incidencia INTEGER PRIMARY KEY, id_partido INTEGER, id_jugador INTEGER, tipo_tarjeta TEXT, descripcion TEXT, fecha_incidencia TEXT, fecha_suspension TEXT, id_jornada_suspension INTEGER)');
$db->exec("INSERT INTO equipo VALUES (1, 'A'), (2, 'B'), (3, 'C')");
$db->exec("INSERT INTO jornada VALUES (1, 1, '2026-10-03', NULL), (2, 2, '2026-10-10', NULL)");
$db->exec("INSERT INTO jugador VALUES (1, 1, 'Ana', 'Uno'), (2, 2, 'Beto', 'Dos'), (3, 3, 'Caro', 'Tres')");
$db->exec("INSERT INTO partido VALUES (1, 1, 1, 2, '2026-10-03')");
$repository = new IncidenciaRepository($db);
$eligible = $repository->playersForMatch(1);
check(count($eligible) === 2 && !in_array(3, array_column($eligible, 'id_jugador'), false),
    'La selección de jugadores incluye un equipo ajeno.');
$repository->insert($validator->validate($red, $partido, $jugador, $jornada)['data']);
check($repository->find(1)['fecha_suspension'] === '2026-10-10', 'No se guardó la roja.');
$repository->update(1, $validator->validate($yellow, $partido, $jugador, $jornada)['data']);
check($repository->find(1)['fecha_suspension'] === null
    && $repository->find(1)['id_jornada_suspension'] === null, 'La edición no limpió la suspensión.');
check(count($repository->findAll(['id_equipo' => 2, 'id_jugador' => 2, 'tipo_tarjeta' => 'amarilla'])) === 1,
    'Falló el filtro combinado.');
check($repository->findAll(['id_equipo' => 1]) === [], 'El filtro por equipo devolvió otro equipo.');
check($repository->delete(1) && $repository->find(1) === null, 'Falló la eliminación aislada.');

$factory = new ServerRequestFactory();
$sessionMiddleware = new SessionMiddleware(['save_path' => dirname(__DIR__) . '/var/sessions']);
$csrf = new IncidenciaCsrf();
$initialHandler = new class($csrf) implements RequestHandlerInterface {
    public string $token = '';

    public function __construct(private readonly IncidenciaCsrf $csrf)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        check(session_status() === PHP_SESSION_ACTIVE, 'SessionMiddleware no inició la sesión.');
        $this->token = $this->csrf->token();
        (new Flash())->success('Mensaje de prueba');

        return new Response(204);
    }
};
$sessionMiddleware->process($factory->createServerRequest('GET', '/incidencias'), $initialHandler);
check(session_status() !== PHP_SESSION_ACTIVE, 'SessionMiddleware no cerró la sesión.');
$token = $initialHandler->token;
$flashHandler = new class($csrf, $token) implements RequestHandlerInterface {
    public array $messages = [];
    public bool $validToken = false;

    public function __construct(private readonly IncidenciaCsrf $csrf, private readonly string $token)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->messages = (new Flash())->getMessages();
        $this->validToken = $this->csrf->validToken($this->token);

        return new Response(204);
    }
};
$sessionMiddleware->process($factory->createServerRequest('GET', '/incidencias'), $flashHandler);
check($flashHandler->messages === ['success' => ['Mensaje de prueba']] && $flashHandler->validToken,
    'Flash o CSRF no sobrevivió al ciclo de sesión.');
$middleware = new IncidenciaCsrfMiddleware($csrf);
$handler = new class implements RequestHandlerInterface {
    public int $calls = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->calls++;

        return new Response(204);
    }
};
$csrfWrapper = new class($middleware, $handler) implements RequestHandlerInterface {
    public function __construct(
        private readonly IncidenciaCsrfMiddleware $middleware,
        private readonly RequestHandlerInterface $handler
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->handler);
    }
};
$invalidRequest = $factory->createServerRequest('POST', '/incidencias')->withParsedBody(['_csrf' => 'invalid']);
check($sessionMiddleware->process($invalidRequest, $csrfWrapper)->getStatusCode() === 403 && $handler->calls === 0,
    'No se rechazó el CSRF inválido.');
$validRequest = $factory->createServerRequest('POST', '/incidencias')->withParsedBody(['_csrf' => $token]);
check($sessionMiddleware->process($validRequest, $csrfWrapper)->getStatusCode() === 204 && $handler->calls === 1,
    'Se rechazó un token CSRF válido.');
$failingHandler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new PDOException('password=secret');
    }
};
$errorMiddleware = new IncidenciaErrorMiddleware();
$failure = $errorMiddleware->process($factory->createServerRequest('GET', '/incidencias'), $failingHandler);
check($failure->getStatusCode() === 503 && !str_contains((string) $failure->getBody(), 'secret'),
    'Se expuso un error interno de base de datos.');
$missingHandler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new HttpNotFoundException($request);
    }
};
check($errorMiddleware->process($factory->createServerRequest('GET', '/incidencias/999/editar'), $missingHandler)
    ->getStatusCode() === 404, 'No se devolvió 404 para una incidencia inexistente.');
$otherModulePassedThrough = false;
try {
    $errorMiddleware->process($factory->createServerRequest('GET', '/equipos/999'), $missingHandler);
} catch (HttpNotFoundException) {
    $otherModulePassedThrough = true;
}
check($otherModulePassedThrough, 'El middleware de Incidencias alteró los errores de otro módulo.');

// Flujo HTTP completo con el mismo SQLite en memoria: nunca usa la base local.
function isolatedApp(PDO $db): Slim\App
{
    $builder = new DI\ContainerBuilder();
    $builder->addDefinitions([
        'settings' => ['app' => ['debug' => false]],
        PDO::class => $db,
        Flash::class => static fn (): Flash => new Flash(),
        IncidenciaCsrf::class => static fn (): IncidenciaCsrf => new IncidenciaCsrf(),
        IncidenciaRepository::class => static fn (Psr\Container\ContainerInterface $c): IncidenciaRepository =>
            new IncidenciaRepository($c->get(PDO::class)),
        Slim\Views\Twig::class => static function (Psr\Container\ContainerInterface $c): Slim\Views\Twig {
            $view = Slim\Views\Twig::create(dirname(__DIR__) . '/templates');
            $view->getEnvironment()->addGlobal('appName', 'Torneo de prueba');
            $view->getEnvironment()->addGlobal('flash', $c->get(Flash::class));

            return $view;
        },
    ]);
    $container = $builder->build();
    Slim\Factory\AppFactory::setContainer($container);
    $app = Slim\Factory\AppFactory::create();
    (require dirname(__DIR__) . '/config/middleware.php')($app);
    (require dirname(__DIR__) . '/config/routes.php')($app);

    return $app;
}

$createResponse = isolatedApp($db)->handle(
    $factory->createServerRequest('GET', 'http://localhost/incidencias/nueva')
);
check($createResponse->getStatusCode() === 200, 'El formulario aislado no carga: ' . $createResponse->getStatusCode());
preg_match('/name="_csrf" value="([^"]+)"/', (string) $createResponse->getBody(), $tokenMatch);
check(isset($tokenMatch[1]), 'El formulario aislado no incluye CSRF.');
$redPost = $red + ['_csrf' => $tokenMatch[1]];
$redPost['_csrf'] = $tokenMatch[1];
$saveResponse = isolatedApp($db)->handle(
    $factory->createServerRequest('POST', 'http://localhost/incidencias')->withParsedBody($redPost)
);
check($saveResponse->getStatusCode() === 303 && $repository->find(1)['tipo_tarjeta'] === 'roja',
    'El registro HTTP aislado no guardó la roja o no redirigió.');
$listResponse = isolatedApp($db)->handle(
    $factory->createServerRequest('GET', 'http://localhost/incidencias')
);
check($listResponse->getStatusCode() === 200
    && str_contains((string) $listResponse->getBody(), 'Incidencia registrada.'),
    'El mensaje Flash del registro no aparece en el listado.');
$nextResponse = isolatedApp($db)->handle(
    $factory->createServerRequest('GET', 'http://localhost/incidencias')
);
check(!str_contains((string) $nextResponse->getBody(), 'Incidencia registrada.'),
    'El mensaje Flash apareció más de una petición.');
$yellowPost = $yellow;
$yellowPost['_csrf'] = $tokenMatch[1];
$updateResponse = isolatedApp($db)->handle(
    $factory->createServerRequest('POST', 'http://localhost/incidencias/1/editar')->withParsedBody($yellowPost)
);
check($updateResponse->getStatusCode() === 303 && $repository->find(1)['fecha_suspension'] === null
    && $repository->find(1)['id_jornada_suspension'] === null,
    'La edición HTTP aislada no convirtió roja a amarilla con suspensión NULL.');

echo "OK: validación, relación jugador-partido, persistencia SQLite aislada, rutas, Flash y CSRF.\n";
