# Documentación de la Estructura de Archivos

Esta documentación describe la estructura de archivos del proyecto **imt-cursos** y explica la funcionalidad de sus carpetas y archivos principales.

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

**Nota**: Para más detalles sobre otros archivos, revisa directamente el repositorio.