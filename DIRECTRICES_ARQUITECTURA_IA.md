# 📋 Directrices de Arquitectura y Reglas para IAs (Slim 4)

Este documento es la **guía oficial de desarrollo y referencia técnica** para todos los colaboradores del proyecto. Si vas a utilizar asistentes de Inteligencia Artificial (**ChatGPT, Claude, Gemini, GitHub Copilot, Cursor**, etc.) para programar tu módulo asignado, **debes copiar y pegar el prompt maestro indicado al final de este documento al inicio de cada conversación**.

---

## 🏗️ Stack Tecnológico Oficial del Proyecto

* **Lenguaje:** PHP 8.2 (tipado estricto `declare(strict_types=1);` obligatorio en todos los archivos).
* **Micro-framework:** Slim Framework 4 (`^4.15`) bajo estándares PSR-7, PSR-11 y PSR-15.
* **Contenedor de Inyección de Dependencias:** PHP-DI (`^7.1`) mediante inyección por constructor (`readonly` properties).
* **Motor de Plantillas:** Twig (`slim/twig-view ^3.4`) con layout base unificado en `templates/layouts/base.twig`.
* **Frontend y Estilos:** Bootstrap 5.3.3 + Bootstrap Icons.
* **Gestor de Base de Datos:** MariaDB / MySQL utilizando **PDO nativo** con sentencias preparadas.
* **Servidor de Desarrollo:** `composer dev` (ejecuta `php -S localhost:8000 -t public`).

---

## 🚫 Reglas Inviolables de Código (Lo que la IA NUNCA debe hacer)

### 1. PROHIBIDO mezclar frameworks
* **No usar sintaxis de Laravel** (prohibido `DB::table`, `Route::get`, `redirect()`, `view()`, helpers de Blade `@if`, `response()->json()`, facades o Eloquent).
* **No usar Symfony, CodeIgniter ni Yii**. Este proyecto es **Slim 4 puro con PSR-7**.

### 2. PROHIBIDO usar superglobales directas en Controladores o Servicios
* ❌ Prohibido: `$_POST`, `$_GET`, `$_FILES`, `$_SERVER` o manipular `$_SESSION` manualmente en controladores.
* ✔️ Datos de formularios o JSON: `$data = (array) $request->getParsedBody();`
* ✔️ Parámetros de consulta URL: `$queryParams = $request->getQueryParams();`
* ✔️ Archivos subidos: `$files = $request->getUploadedFiles();`
* ✔️ Parámetros de ruta dinámica: `$args['id']`
* ✔️ Sesiones y mensajes: Gestionados por `SessionMiddleware` y `Flash`.

### 3. PROHIBIDO escribir SQL dentro de los Controladores
* **Cero sentencias SQL en controladores.** Todo `SELECT`, `INSERT`, `UPDATE` o `DELETE` debe residir **exclusivamente** dentro de su clase correspondiente en `src/Repositories/` (`PDO` con prepared statements).
* El controlador únicamente orquesta la petición: valida entrada, delega al repositorio, invoca servicios y retorna la respuesta.

### 4. PROHIBIDO reinventar o duplicar servicios y utilidades existentes
Antes de crear nuevas funciones o helpers, la IA **debe reutilizar** los componentes arquitectónicos ya implementados:
* **Subida y eliminación de imágenes:** Usar exclusivamente `App\Services\UploadService`.
  * Subir: `$fotoUrl = $this->uploader->upload($file, 'subcarpeta');` (valida MIME Type real con `finfo`, peso máx. 2MB y genera hash único).
  * Borrar: `$this->uploader->delete($fotoUrl);` (con protección estricta contra *Directory Traversal*).
* **Mensajes Flash:** Inyectar y usar `App\Support\Flash`.
  * `$this->flash->success('Mensaje exitoso');`
  * `$this->flash->error('Mensaje de error');` (se mapea a `.alert-danger` de Bootstrap 5).
  * `$this->flash->warning('Advertencia');`
  * Twig ya tiene inyectada la variable global `flash`, por lo que **no es necesario pasar los mensajes manualmente al renderizar la vista**.
* **Gestión de Sesión:** Ya está resuelta mediante `App\Middleware\SessionMiddleware`. No uses `session_start()` en controladores.

### 5. PROHIBIDO romper el ciclo de vida HTTP de Slim (`exit`, `die`, `echo`)
* ❌ Prohibido `echo $vista;`, `print_r();`, `die();` o `exit();`.
* ✔️ Para renderizar vistas:
  ```php
  return $this->view->render($response, 'modulo/vista.twig', [
      'title' => 'Título de la Página',
      'items' => $items,
  ]);
  ```
* ✔️ Para redirecciones HTTP (Post-Redirect-Get):
  ```php
  return $response->withHeader('Location', '/modulo')->withStatus(302);
  ```

### 6. Obligatorio: Transaccionalidad en dos fases (Rollback de Archivos)
Si un endpoint sube una fotografía física a disco e intenta persistir el registro en la base de datos vía PDO, **debe implementar un bloque `try/catch` con rollback de archivo**:
```php
$fotoUrl = null;
try {
    $fotoUrl = $this->uploader->upload($fotoFile, 'jugadores');
    $this->jugadores->create(..., $fotoUrl);
    $this->flash->success('Registro completado con éxito.');

    return $response->withHeader('Location', '/jugadores')->withStatus(302);
} catch (Throwable $e) {
    // Si la base de datos falla, se elimina la foto física recién subida para no dejar basura
    if ($fotoUrl !== null) {
        $this->uploader->delete($fotoUrl);
    }
    $this->flash->error('Error al guardar: ' . $e->getMessage());

    return $response->withHeader('Location', '/jugadores/crear')->withStatus(302);
}
```

---

## 🗄️ Esquema Oficial de la Base de Datos (`database/schema.sql`)

La IA debe respetar estrictamente los nombres de tablas, columnas y tipos definidos en el esquema oficial:

| Tabla | Columnas Clave | Restricciones y Notas |
| :--- | :--- | :--- |
| **`equipo`** | `id_equipo`, `nombre` (VARCHAR 100, UNIQUE), `foto_url` (VARCHAR 255, NULL), `creado_en` | No tiene dependencias foráneas. |
| **`jornada`** | `id_jornada`, `numero_jornada` (INT, UNIQUE), `fecha_juego` (DATE), `descripcion` (VARCHAR 150) | Representa las fechas del torneo. |
| **`jugador`** | `id_jugador`, `id_equipo`, `nombres`, `apellidos`, `fecha_nacimiento` (DATE), `foto_url` (VARCHAR 255), `creado_en` | `FK id_equipo -> equipo.id_equipo` (`ON DELETE RESTRICT`). |
| **`partido`** | `id_partido`, `id_jornada`, `id_equipo_local`, `id_equipo_visita`, `fecha` (DATE), `creado_en` | `CHECK (id_equipo_local <> id_equipo_visita)`. `FK` hacia `jornada` y `equipo`. |
| **`gol`** | `id_gol`, `id_jugador`, `id_partido`, `cantidad` (INT DEFAULT 1), `creado_en` | `UNIQUE (id_jugador, id_partido)`. `FK` cascada a `jugador` y `partido`. |
| **`incidencia`** | `id_incidencia`, `id_jugador`, `id_partido`, `tipo_tarjeta` (ENUM `'amarilla'`, `'roja'`), `descripcion` (TEXT), `fecha_incidencia` (DATE), `fecha_suspension` (DATE NULL), `id_jornada_suspension` (INT NULL), `creado_en` | `FK` a `jugador`, `partido` y `jornada` (`id_jornada_suspension`). |

---

## 📁 Estructura del Proyecto y Dónde Escribir Código

```text
torneo-futbol-slim/
├── config/
│   ├── container.php         # Registro de dependencias en PHP-DI (Repositorios, Servicios, Twig)
│   ├── middleware.php        # Pipeline LIFO de Middlewares (Error, Session, Routing, Twig, Body)
│   ├── routes.php            # Declaración y agrupamiento de rutas HTTP de la aplicación
│   └── settings.php          # Credenciales de BD y configuraciones de entorno
├── database/
│   ├── schema.sql            # Estructura DDL oficial de tablas y relaciones
│   └── seeds.sql             # Datos de prueba iniciales
├── public/
│   ├── uploads/              # Carpeta de destino de archivos (/equipos, /jugadores)
│   └── index.php             # Punto de entrada de Slim
├── src/
│   ├── Controllers/          # Clases controladoras (un controlador por recurso)
│   ├── Middleware/           # Middlewares PSR-15 (SessionMiddleware, etc.)
│   ├── Repositories/         # Clases de acceso a datos con PDO nativo
│   ├── Services/             # Lógica de soporte (UploadService)
│   └── Support/              # Clases de utilidad transversal (Flash)
└── templates/
    ├── layouts/
    │   └── base.twig         # Plantilla maestra (Navbar, contenedor de alertas Flash, Footer)
    ├── equipos/              # Vistas de Equipos (index.twig, form.twig)
    ├── jugadores/            # Vistas de Jugadores (index.twig, form.twig)
    └── [tu_modulo]/          # Crear aquí las vistas de módulos futuros (partidos, goles, etc.)
```

---

## 🧩 Patrones de Código de Referencia

### 1. Repositorio Canónico (`src/Repositories/EjemploRepository.php`)
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EjemploRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function all(): array
    {
        $sql = 'SELECT * FROM tabla ORDER BY id DESC';
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT * FROM tabla WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $item = $stmt->fetch();
        return $item ?: null;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO tabla (campo1, campo2) VALUES (:campo1, :campo2)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'campo1' => $data['campo1'],
            'campo2' => $data['campo2'],
        ]);

        return (int) $this->db->lastInsertId();
    }
}
```

### 2. Registro en el Contenedor (`config/container.php`)
Al crear un nuevo repositorio o servicio, regístralo con inyección automática de PDO:
```php
EjemploRepository::class => function (ContainerInterface $c): EjemploRepository {
    return new EjemploRepository($c->get(PDO::class));
},
```

### 3. Registro de Rutas en Grupos (`config/routes.php`)
Reutiliza los grupos ya previstos en el archivo de rutas:
```php
$app->group('/partidos', function (RouteCollectorProxy $group): void {
    $group->get('', [PartidoController::class, 'index'])->setName('partidos.index');
    $group->get('/crear', [PartidoController::class, 'create'])->setName('partidos.create');
    $group->post('', [PartidoController::class, 'store'])->setName('partidos.store');
    $group->get('/{id:[0-9]+}/editar', [PartidoController::class, 'edit'])->setName('partidos.edit');
    $group->post('/{id:[0-9]+}/editar', [PartidoController::class, 'update'])->setName('partidos.update');
    $group->post('/{id:[0-9]+}/eliminar', [PartidoController::class, 'delete'])->setName('partidos.delete');
});
```
U algún otro grupo que se encuentre en routes.php.

### 4. Plantilla Twig de Módulo (`templates/modulo/index.twig`)
Heredar siempre de `layouts/base.twig` y definir bloques de contenido:
```twig
{% extends 'layouts/base.twig' %}

{% block title %}{{ title }} - {{ appName }}{% endblock %}

{% block content %}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1">{{ title }}</h2>
        <p class="text-muted small mb-0">Gestión y control del módulo.</p>
    </div>
    <a href="{{ url_for('partidos.create') }}" class="btn btn-success shadow-sm">
        <i class="bi bi-plus-circle me-1"></i> Registrar Partido
    </a>
</div>

{# Contenido de tablas, cards o métricas con Bootstrap 5 #}
{% endblock %}
```

---

## 🤖 Prompt Maestro para usar con cualquier IA

> **INSTRUCCIONES PARA EL COLABORADOR:**  
> Copia todo el bloque dentro de las comillas triples y pégalo como tu **primer mensaje** en el chat de IA (ChatGPT, Claude, Gemini, Copilot, etc.) antes de solicitar la generación de código:

```text
Actúa como un Arquitecto de Software Senior en PHP. Estoy colaborando en un proyecto universitario con una arquitectura estricta que DEBES seguir rigurosamente:

1. ARQUITECTURA TÉCNICA:
   - Framework: Slim 4 puro (^4.15) con PHP 8.2 y tipado estricto (declare(strict_types=1);).
   - Inyección de Dependencias: PHP-DI (^7.1) mediante constructores con propiedades readonly.
   - Vistas: Twig (slim/twig-view) con Bootstrap 5.3 y Bootstrap Icons, heredando siempre de "layouts/base.twig".
   - Persistencia: MariaDB / MySQL utilizando PDO nativo y sentencias preparadas ($stmt->prepare, $stmt->execute).
   - Capas de la aplicación:
     * src/Controllers/ : Controladores que reciben ServerRequestInterface y ResponseInterface de PSR-7.
     * src/Repositories/ : Acceso a datos con PDO (TODO el SQL vive aquí, jamás en el controlador).
     * src/Services/ : Lógica reutilizable. Para imágenes usar SIEMPRE App\Services\UploadService.
     * src/Support/ : Clases auxiliares. Para alertas usar SIEMPRE App\Support\Flash.
     * config/routes.php : Agrupación de rutas HTTP con RouteCollectorProxy.
     * config/container.php : Definición de repositorios y servicios para PHP-DI.

2. RESTRICCIONES INVIOLABLES:
   - PROHIBIDO usar sintaxis de Laravel (no DB::, no Route::, no helpers de Blade, no Eloquent).
   - PROHIBIDO usar superglobales directas ($_POST, $_GET, $_FILES, $_SESSION, $_SERVER) en controladores. Usa los métodos PSR-7 de $request.
   - PROHIBIDO usar echo, print, exit() o die(). Todo retorno debe ser una ResponseInterface.
   - REUTILIZACIÓN OBLIGATORIA: No inventes nuevos métodos de subida de fotos ni de alertas flash; reutiliza UploadService y Flash.
   - ROLLBACK EN DISCO: Si se sube una foto y falla la inserción en BD, en el bloque catch(Throwable $e) se debe llamar a $this->uploader->delete($fotoUrl) para no dejar archivos huérfanos.

3. ESQUEMA DE BASE DE DATOS (Nombres exactos de tablas y columnas):
   - equipo (id_equipo, nombre, foto_url, creado_en)
   - jornada (id_jornada, numero_jornada, fecha_juego, descripcion)
   - jugador (id_jugador, id_equipo, nombres, apellidos, fecha_nacimiento, foto_url, creado_en)
   - partido (id_partido, id_jornada, id_equipo_local, id_equipo_visita, fecha, creado_en)
   - gol (id_gol, id_jugador, id_partido, cantidad, creado_en)
   - incidencia (id_incidencia, id_jugador, id_partido, tipo_tarjeta ['amarilla','roja'], descripcion, fecha_incidencia, fecha_suspension, id_jornada_suspension, creado_en)

4. MI REQUERIMIENTO A DESARROLLAR:
   [ESCRIBE AQUÍ CON DETALLE EL MÓDULO O ENDPOINTS QUE TE CORRESPONDE HACER, EJ: Crear PartidoController, PartidoRepository, rutas en config/routes.php y las vistas templates/partidos/index.twig y form.twig]
```
