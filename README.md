# IMT Cursos - Sistema de Gestión de Cursos

## Descripción del Proyecto

IMT Cursos es un sistema web de gestión de cursos desarrollado en PHP que permite la administración y visualización de cursos educativos con diferentes roles de usuario.

## Estructura del Proyecto

imt-cursos/
├── app/
│   └── auth.php                 # Sistema de autenticación y control de roles
├── public/
│   ├── docente/
│   │   └── visualizar_curso.php # Vista de curso para docentes
│   └── partials/
│       ├── header.php           # Cabecera común de páginas
│       ├── nav.php              # Barra de navegación
│       └── footer.php           # Pie de página común
└── README.md                    # Este archivo

## Características Implementadas

### Sistema de Autenticación
- Archivo: `app/auth.php`
- Control de acceso basado en roles
- Función `require_role()` para validar permisos de usuario
- Roles implementados: `docente`

### Módulo de Docentes
- **Visualización de Cursos** (`public/docente/visualizar_curso.php`)
  - Vista dedicada para que los docentes puedan ver información de sus cursos
  - Funcionalidades planificadas:
    - Lista de estudiantes inscritos
    - Porcentaje de avance por estudiante
    - Fechas de inicio y compleción de cursos

### Sistema de Plantillas
- **Header** (`public/partials/header.php`): Estructura HTML común y metadatos
- **Navegación** (`public/partials/nav.php`): Menú de navegación del sistema
- **Footer** (`public/partials/footer.php`): Pie de página común

## Tecnologías Utilizadas

- **Backend**: PHP
- **Servidor**: Laragon (entorno de desarrollo local)
- **Arquitectura**: MVC básico con separación de concerns


## Estructura de Roles y Permisos

### Docente
- **Acceso**: Módulo de docentes
- **Funcionalidades**:
  - Visualización de cursos asignados
  - Monitoreo de progreso de estudiantes
  - Gestión de fechas de curso

## Estado Actual del Desarrollo

### ✅ Completado
- Sistema básico de autenticación por roles
- Estructura de plantillas reutilizables
- Página base para visualización de cursos (docentes)


## Convenciones de Código

- Archivos PHP con extensión `.php`
- Uso de `require_once` para evitar inclusiones duplicadas
- Separación de lógica de autenticación en `app/auth.php`
- Plantillas reutilizables en `public/partials/`
- Estructura de URLs amigables por módulos
