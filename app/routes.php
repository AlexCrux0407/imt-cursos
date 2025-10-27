<?php
/**
 * Definición de rutas principales de la aplicación IMT-Cursos.
 */
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/EstudianteController.php';
require_once __DIR__ . '/controllers/DocenteController.php';
require_once __DIR__ . '/controllers/MasterController.php';
require_once __DIR__ . '/controllers/EjecutivoController.php';

$router = new Router();

// RUTAS PÚBLICAS
$router->get('/', function() {
    if (is_logged_in()) {
        $role = $_SESSION['role'] ?? '';
        switch ($role) {
            case 'estudiante':
                header('Location: /estudiante/dashboard');
                break;
            case 'docente':
                header('Location: /docente/dashboard');
                break;
            case 'master':
                header('Location: /master/dashboard');
                break;
            case 'ejecutivo':
                header('Location: /ejecutivo/dashboard');
                break;
            default:
                header('Location: /login.php');
        }
    } else {
        header('Location: /login.php');
    }
    exit;
});

// AUTENTICACIÓN
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// ESTUDIANTE
$router->get('/estudiante/dashboard', 'EstudianteController@dashboard', ['AuthMiddleware']);
$router->get('/estudiante/catalogo', 'EstudianteController@catalogo', ['AuthMiddleware']);
$router->get('/estudiante/mis-cursos', 'EstudianteController@misCursos', ['AuthMiddleware']);
$router->get('/estudiante/curso/{id}', 'EstudianteController@verCurso', ['AuthMiddleware']);
$router->get('/estudiante/evaluacion/{evaluacion_id}/resultado', 'EstudianteController@resultadoEvaluacion', ['AuthMiddleware']);

// DOCENTE
$router->get('/docente/dashboard', 'DocenteController@dashboard', ['AuthMiddleware', 'RoleMiddleware:docente']);
$router->get('/docente/admin-cursos', 'DocenteController@adminCursos', ['AuthMiddleware', 'RoleMiddleware:docente']);
$router->get('/docente/editar-curso/{id}', 'DocenteController@editarCurso', ['AuthMiddleware', 'RoleMiddleware:docente']);
$router->post('/docente/procesar-curso/{id?}', 'DocenteController@procesarCurso', ['AuthMiddleware', 'RoleMiddleware:docente']);
$router->get('/docente/modulos-curso/{id}', 'DocenteController@modulosCurso', ['AuthMiddleware', 'RoleMiddleware:docente']);
$router->get('/docente/reportes', 'DocenteController@reportes', ['AuthMiddleware', 'RoleMiddleware:docente']);

// MASTER
$router->get('/master/dashboard', 'MasterController@dashboard', ['AuthMiddleware', 'RoleMiddleware:master']);
$router->get('/master/admin-cursos', 'MasterController@adminCursos', ['AuthMiddleware', 'RoleMiddleware:master']);
$router->get('/master/asignar-cursos', 'MasterController@asignarCursos', ['AuthMiddleware', 'RoleMiddleware:master']);
$router->post('/master/procesar-asignacion', 'MasterController@procesarAsignacion', ['AuthMiddleware', 'RoleMiddleware:master']);
$router->get('/master/editar-curso/{id}', 'MasterController@editarCurso', ['AuthMiddleware', 'RoleMiddleware:master']);
$router->post('/master/procesar-curso/{id?}', 'MasterController@procesarCurso', ['AuthMiddleware', 'RoleMiddleware:master']);

// EJECUTIVO
$router->get('/ejecutivo/dashboard', 'EjecutivoController@dashboard', ['AuthMiddleware', 'RoleMiddleware:ejecutivo']);
$router->get('/ejecutivo/reportes', 'EjecutivoController@reportes', ['AuthMiddleware', 'RoleMiddleware:ejecutivo']);
$router->get('/ejecutivo/analytics', 'EjecutivoController@analytics', ['AuthMiddleware', 'RoleMiddleware:ejecutivo']);
$router->get('/ejecutivo/exportar-reporte', 'EjecutivoController@exportarReporte', ['AuthMiddleware', 'RoleMiddleware:ejecutivo']);

/**
 * Ruta stub para evitar 404 del cliente de Vite en entornos sin bundler.
 */
$router->get('/@vite/client', function() {
    header('Content-Type: application/javascript');
    echo "// Vite client deshabilitado";
});

return $router;