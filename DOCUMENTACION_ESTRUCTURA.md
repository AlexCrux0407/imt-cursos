# Documentación de la Estructura de Archivos

Esta guía describe la estructura del proyecto, sus directorios clave, cómo se sirven los recursos (medios y certificados), y prácticas recomendadas para extender y mantener la plataforma.

## Resumen

- Plataforma de cursos en PHP (PDO) + MySQL, con Composer para dependencias.
- Roles: `master`, `docente`, `ejecutivo`, `estudiante` con protección por `require_role()`.
- Páginas por rol en `public/<rol>/` y utilidades de medios en `public/serve_*.php`.
- Configuración de plataforma (incluye video de bienvenida) gestionada por el rol `master`.

## Estructura General

```
imt-cursos-local/
├── app/                  # Núcleo de aplicación (router, controladores, auth, helpers)
├── config/               # Configuración de base de datos y rutas/paths
├── public/               # Front controllers, páginas por rol y servicios HTTP
│   ├── estudiante/       # Vistas y flujos del estudiante
│   ├── docente/          # Administración de cursos, contenidos y evaluaciones
│   ├── ejecutivo/        # Reportes y vistas ejecutivas
│   ├── master/           # Administración global y configuración de plataforma
│   ├── partials/         # Header, footer, navegación compartida
│   ├── styles/           # CSS, JS y assets
│   ├── uploads/          # Medios subidos (certificados, cursos, media)
│   ├── serve_media.php   # Proxy seguro para servir medios configurados
│   ├── serve_uploads.php # Proxy para archivos en uploads (controlado)
│   └── serve_certificado_template.php # Servicio para plantillas de certificados
├── uploads/              # Raíz de almacenamiento (según despliegue)
└── vendor/               # Dependencias Composer
```

## Directorios y archivos clave

### `app/`
- `Router.php`: Mínimo ruteo; muchas páginas están servidas como scripts directos en `public/`.
- `controllers/`: Controladores por rol (p. ej. `MasterController.php`).
- `auth.php`: Autenticación, sesión y helpers `require_role('<rol>')`.
- `upload_helper.php`: Utilidades para manejo seguro de subidas.
- `curso_scanner.php`: Helper para escaneo/carga de contenidos de cursos.

### `config/`
- `database.php`: Conexión PDO y parámetros de base de datos.
- `paths.php`: Constantes como `BASE_URL`, `ROOT_PATH`, `PUBLIC_PATH` y rutas de uploads.

### `public/`
- `index.php`, `login.php`, `logout.php`: Entradas generales.
- `partials/`: `header.php`, `footer.php`, `nav.php` usados entre vistas.
- `styles/`: Recursos estáticos (CSS, JS, iconos, logos).
- `uploads/`: Estructura de subidas accesibles por proxy.
  - `certificados/`: Plantillas y resultados de certificados.
  - `cursos/`, `media/`: Contenidos de cursos y medios.
- Servicios:
  - `serve_media.php`: Sirve contenidos de plataforma (p. ej. video de bienvenida) con detección de MIME y control de caché.
  - `serve_uploads.php`: Sirve archivos de `uploads/` con validaciones.
  - `serve_certificado_template.php`: Resuelve y sirve plantillas de certificados, con búsqueda y fallbacks.

### `public/master/`
- Panel de administración global (alta/baja de usuarios, cursos, y configuración).
- `admin_plataforma.php`: Configuración de la plataforma, incluye `video_bienvenida`.
- `procesar_plataforma.php`: Persistencia de cambios de configuración.
- `configurar_certificado.php` y `procesar_certificado.php`: Gestión de plantillas y posiciones de texto en certificados.

### `public/estudiante/`
- `dashboard.php`: Página principal del estudiante. Muestra el video de bienvenida y la información destacada (hero).
  - Integra el video desde `configuracion_plataforma.video_bienvenida` a través de `serve_media.php`.
  - Fallback automático a `uploads/media/bienvenida.mp4` si no hay configuración válida.
- `mis_cursos.php`, `curso_contenido.php`, `leccion.php`, `tomar_evaluacion.php`: Flujo de consumo de cursos.
- `evaluacion_organigrama.php` y `procesar_intento_evaluacion.php`: Flujo de evaluación del organigrama y su procesamiento.
- `certificado.php` y `generar_certificado.php`: Visualización y generación de certificados.

### `public/docente/`
- Administración y edición de cursos, módulos, lecciones y evaluaciones.
- Páginas para CRUD y orden de contenidos, calificación, y reportes docentes.

### `public/ejecutivo/`
- Vistas de reporte y detalle de estudiantes/cursos, exportaciones y dashboards ejecutivos.

## Medios y certificados

### Video de bienvenida (hero)
- Configuración: el rol `master` define `video_bienvenida` en `admin_plataforma.php`.
- Servicio: `estudiante/dashboard.php` obtiene el video vía `serve_media.php?tipo=video_bienvenida`, con detección de tipo (`mp4`, `webm`, `ogg`, etc.).
- Fallback: si no existe configuración o no es legible, se usa `uploads/media/bienvenida.mp4` si está disponible.

### Certificados
- Configuración por curso en `master/configurar_certificado.php` con campos (fuente, tamaño, color, posiciones).
- `procesar_certificado.php` gestiona el guardado y resolución de destinos.
- `serve_certificado_template.php` busca la plantilla en ubicaciones conocidas y, si no hay permisos de escritura, aplica fallbacks a `/tmp/imt-cursos/uploads/certificados` (comúnmente escribible).
- Opción de debug: `?debug=1` devuelve trazas JSON para diagnóstico de rutas.

## Autenticación y autorización
- `app/auth.php` maneja sesión y helpers: `require_role('estudiante'|'docente'|'ejecutivo'|'master')`.
- Todas las páginas sensibles en `public/<rol>/` comienzan validando el rol.

## Rutas y navegación
- Muchas rutas son scripts directos en `public/` organizados por rol.
- `app/routes.php` puede mapear rutas adicionales si se requiere una capa de ruteo más centralizada.

## Subidas y almacenamiento
- Subidas se realizan en subdirectorios de `public/uploads/` con control de acceso mediante los servicios `serve_*`.
- En despliegues con restricciones, se habilitan fallbacks a directorios temporales (`/tmp/imt-cursos/uploads`) para evitar bloqueos por permisos.

## Scripts de diagnóstico y mantenimiento (estado actual)

Se han eliminado scripts de diagnóstico y mantenimiento no necesarios en producción:
- `public/env_check.php`, `public/db_ping.php`: verificación de entorno/DB (ahora se recomienda usar logs y páginas funcionales).
- `public/master/debug_certificados.php`: inspección de tabla de certificados (reemplazable por consultas SQL directas en la DB).
- `fix_sql_syntax_final.php`: script puntual de corrección de sintaxis SQL.
- `crear_evaluacion_organigrama_correcto.php`: script único para creación de evaluación.

Alternativas de verificación sin estos scripts:
- Conexión DB: acceder a `public/login.php` y revisar `error_log`/logs del servidor si hay fallos.
- Certificados: usar `master/configurar_certificado.php` y `serve_certificado_template.php?debug=1` para trazas.
- Estado de medios: comprobar `estudiante/dashboard.php` y validar que el video se sirve vía `serve_media.php`.

## Convenciones y buenas prácticas
- Uso de PDO con consultas preparadas y parámetros nombrados.
- Sanitización de salida HTML mediante `htmlspecialchars()`.
- No servir archivos directamente desde `uploads/`; usar `serve_media.php` o `serve_uploads.php`.
- Mantener roles y permisos con `require_role()` al inicio de cada script.

## Cómo agregar/actualizar recursos
- Video de bienvenida: subir archivo al almacenamiento accesible y configurar en `master/admin_plataforma.php`. La vista del estudiante lo servirá automáticamente vía `serve_media.php`.
- Contenidos de curso: utilizar las páginas de `public/docente/` para crear/editar módulos, lecciones y evaluaciones.
- Certificados: subir plantilla y ajustar posiciones en `master/configurar_certificado.php`.

## Mantenimiento de esta documentación
- Actualizar este documento cuando se modifique la estructura de directorios o el flujo de medios/certificados.
- Referenciar cambios relevantes en `config/paths.php` y servicios `serve_*` si se alteran rutas o políticas de caché.

---

Última actualización: se integró video de bienvenida configurable en `estudiante/dashboard.php` usando `serve_media.php` y se eliminó utilería de diagnóstico no necesaria en producción.

## Estructura Principal

### `app/auth.php`
- Contiene funciones relacionadas con la autenticación de usuarios, como verificación de sesión activa y manejo de roles.
- [Ver archivo en GitHub](https://github.com/AlexCrux0407/imt-cursos/blob/main/app/auth.php)

### `app/upload_helper.php`
- Proporciona un helper para manejar archivos, organizándolos por curso, módulo y lección.
- [Ver archivo en GitHub](https://github.com/AlexCrux0407/imt-cursos/blob/main/app/upload_helper.php)

### `public/index.php`
- Archivo de entrada principal que verifica la conexión con la base de datos.
- [Ver archivo en GitHub](https://github.com/AlexCrux0407/imt-cursos/blob/main/public/index.php)

## CSS
- `public/styles/css/styles.css`: Estilos generales.
- `public/styles/css/docente.css`: Estilos específicos para el módulo de docentes.
- `public/styles/css/catalogo.css`: Estilos para el catálogo de cursos.
- `public/styles/css/estilosForm.css`: Estilos para formularios.

## Vista del Docente
- `public/docente/dashboard.php`: Dashboard para gestionar cursos y supervisar progreso de estudiantes.
- [Ver archivo en GitHub](https://github.com/AlexCrux0407/imt-cursos/blob/main/public/docente/dashboard.php)

## Vista del Estudiante
- `public/estudiante/leccion.php`: Muestra detalles y navegación de lecciones para estudiantes.
- [Ver archivo en GitHub](https://github.com/AlexCrux0407/imt-cursos/blob/main/public/estudiante/leccion.php)

## Vista del Administrador (Master)
- `public/master/editar_curso.php`: Permite editar información de cursos.
- [Ver archivo en GitHub](https://github.com/AlexCrux0407/imt-cursos/blob/main/public/master/editar_curso.php)

