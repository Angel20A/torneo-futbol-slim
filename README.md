# ⚽ Torneo Infantil de Fútbol - Sistema de Gestión

Sistema web moderno y modular para la administración integral de torneos de fútbol infantil, desarrollado con arquitectura limpia sobre **Slim 4**, **PHP 8.2**, **MariaDB**, **PHP-DI**, **Twig** y **Bootstrap 5**.

---

## 🛠️ Stack Tecnológico

- **Lenguaje:** PHP 8.2+ (con tipado estricto `declare(strict_types=1);`)
- **Micro-framework:** [Slim Framework 4](https://www.slimframework.com/)
- **Inyector de Dependencias (DI):** [PHP-DI 7](https://php-di.org/) (PSR-11)
- **Motor de Plantillas:** [Twig 3](https://twig.symfony.com/) con extensión `slim/twig-view`
- **Base de Datos:** MariaDB / MySQL vía extensión nativa `PDO`
- **Frontend:** Bootstrap 5.3.3 & Bootstrap Icons (vía CDN)
- **Variables de Entorno:** [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)
- **Estándar de Autocarga:** PSR-4 (`App\` mapeado a `src/`)

---

## 📁 Estructura del Proyecto

El proyecto sigue una organización estricta, desacoplada y orientada a capas:

```text
torneo-futbol-slim/
├── config/                     # Configuración y fontanería de la aplicación
│   ├── settings.php            # Lee variables de entorno (.env) y retorna array de configuración
│   ├── container.php           # Definición de dependencias e inyección con PHP-DI (PDO, Twig, etc.)
│   ├── middleware.php          # Registro de middlewares globales (Twig, Routing, Error Handling)
│   └── routes.php              # Enrutador principal y agrupamiento por módulos
│
├── database/                   # Recursos de base de datos
│   └── schema.sql              # Script DDL de creación de base de datos y tablas
│
├── public/                     # Raíz pública del servidor web (Document Root)
│   ├── index.php               # Front Controller (~20 líneas): inicializa Dotenv, Container, App y Run
│   └── uploads/                # Directorio de subida de imágenes y archivos estáticos
│
├── src/                        # Código fuente del sistema bajo el namespace "App\"
│   ├── Controllers/            # Controladores que reciben peticiones y devuelven respuestas HTTP
│   │   └── HomeController.php  # Controlador inicial con diagnóstico de fontanería y estado
│   └── Repositories/           # Capa de Acceso a Datos (Data Access Layer - DAL) para consultas SQL
│
├── templates/                  # Vistas y plantillas de Twig
│   ├── home.twig               # Vista del Dashboard inicial
│   └── layouts/                # Plantillas maestras reutilizables
│       └── base.twig           # Layout principal con navegación responsiva y bloques de contenido
│
├── .env                        # Variables de entorno locales (NO versionar en Git)
├── .env.example                # Plantilla de variables de entorno requeridas
├── .gitignore                  # Exclusión de archivos confidenciales y dependencias
├── composer.json               # Dependencias, scripts y configuración de autoload PSR-4
└── README.md                   # Documentación técnica del proyecto
```

---

## 🚀 Requisitos Previos

1. **PHP:** Versión 8.2 o superior instalada (con extensiones `pdo`, `pdo_mysql`, `mbstring`).
2. **Composer:** Gestor de dependencias de PHP instalado globalmente.
3. **MariaDB / MySQL:** Servidor de base de datos activo (ej. a través de XAMPP, Laragon o Docker).

---

## ⚙️ Instalación y Configuración

### 1. Clonar el repositorio y acceder al directorio
```bash
git clone <url-del-repositorio>
cd torneo-futbol-slim
```

### 2. Instalar dependencias
```bash
composer install
```

### 3. Configurar variables de entorno
Copia el archivo `.env.example` a `.env` (si aún no existe) y ajusta las credenciales de tu base de datos:

```ini
APP_NAME="Torneo Infantil de Fútbol"
APP_ENV=local

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=torneo_infantil
DB_USER=root
DB_PASS=
```

### 4. Inicializar la base de datos
Importa el script SQL en MariaDB para crear la base de datos:
```bash
mysql -u root -p < database/schema.sql
```

### 5. Levantar el servidor de desarrollo
Ejecuta el script preconfigurado en Composer:
```bash
composer dev
```

El sistema estará disponible en tu navegador en:  
👉 **[http://localhost:8000](http://localhost:8000)**

---

## 🧩 Flujo de una Petición (Ciclo de Vida)

```
[ Petición HTTP ]
       │
       ▼
public/index.php (Front Controller)
       │──> Carga de .env (phpdotenv)
       │──> Construcción del contenedor (config/container.php con PHP-DI)
       │──> Inicialización de Slim App
       │──> Registro de middlewares (config/middleware.php)
       │──> Despacho de rutas (config/routes.php)
       │
       ▼
src/Controllers/HomeController.php (Inyección de Twig & PDO)
       │
       ▼
templates/home.twig (Hereda de layouts/base.twig)
       │
       ▼
[ Respuesta HTML al Navegador ]
```

---

## 🛡️ Buenas Prácticas y Seguridad Implementadas

- **Tipado Estricto:** Todos los archivos PHP inician con `declare(strict_types=1);`.
- **Inyección de Dependencias:** Gestión mediante PHP-DI 7 evitando acoplamiento o uso de `global` y `Singletons` estáticos.
- **Seguridad en PDO:**
  - `PDO::ATTR_EMULATE_PREPARES => false`: Forzado de sentencias preparadas nativas del motor, previniendo inyecciones SQL.
  - `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`: Errores manejados como excepciones.
  - `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`: Arreglos asociativos limpios por defecto.
  - Codificación `utf8mb4` para soporte internacional y sanitización de caracteres.
- **Protección del Front Controller:** Únicamente el directorio `public/` se expone al servidor web; el código fuente, configuración y plantillas quedan protegidos fuera del alcance web directo.

---

## 📋 Módulos Planificados (Próximas Fases)

- [ ] **Equipos:** Registro de clubes, categorías, escudos y delegados.
- [ ] **Jugadores:** Padrón infantil con fotos, dorsales, categorías y fichas médicas.
- [ ] **Partidos / Fixture:** Rol de juegos, asignación de canchas, árbitros y horarios.
- [ ] **Goles:** Minuto a minuto y tabla de goleo individual.
- [ ] **Incidencias:** Tarjetas amarillas, rojas y control de Fair Play.
- [ ] **Reportes:** Tabla general de posiciones (PTS, PJ, PG, PE, PP, GF, GC, DIF) y reportes imprimibles.
