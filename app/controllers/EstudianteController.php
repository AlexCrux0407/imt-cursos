<?php

require_once __DIR__ . '/../Controller.php';

/*
 Controlador Estudiante
 Maneja dashboard, catálogo y contenidos de cursos para estudiantes.
*/

class EstudianteController extends Controller
{
    /**
     * Requiere rol estudiante para todas las acciones.
     */
    public function __construct()
    {
        $this->requireRole('estudiante');
    }

    /**
     * Muestra cursos inscritos y estadísticas del estudiante.
     */
    public function dashboard(): void
    {
        try {
            require_once __DIR__ . '/../../config/database.php';
            
            $user_id = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("
                SELECT c.*, ic.fecha_inscripcion, ic.progreso
                FROM cursos c 
                INNER JOIN inscripciones_cursos ic ON c.id = ic.curso_id 
                WHERE ic.usuario_id = ? AND c.activo = 1
                ORDER BY ic.fecha_inscripcion DESC
            ");
            $stmt->execute([$user_id]);
            $cursosInscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM inscripciones_cursos WHERE usuario_id = ?");
            $stmt->execute([$user_id]);
            $totalCursos = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) as completados FROM inscripciones_cursos WHERE usuario_id = ? AND progreso = 100");
            $stmt->execute([$user_id]);
            $cursosCompletados = $stmt->fetchColumn();

            $this->view('estudiante/dashboard', [
                'page_title' => 'Dashboard - Estudiante',
                'cursosInscritos' => $cursosInscritos,
                'totalCursos' => $totalCursos,
                'cursosCompletados' => $cursosCompletados
            ]);
        } catch (Exception $e) {
            error_log("Error en dashboard estudiante: " . $e->getMessage());
            $this->view('estudiante/dashboard', [
                'page_title' => 'Dashboard - Estudiante',
                'error' => 'Error al cargar el dashboard'
            ]);
        }
    }

    /**
     * Lista cursos disponibles que aún no ha inscrito.
     */
    public function catalogo(): void
    {
        try {
            require_once __DIR__ . '/../../config/database.php';
            
            $user_id = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("
                SELECT c.* 
                FROM cursos c 
                WHERE c.activo = 1 
                AND c.id NOT IN (
                    SELECT curso_id FROM inscripciones_cursos WHERE usuario_id = ?
                )
                ORDER BY c.titulo
            ");
            $stmt->execute([$user_id]);
            $cursosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->view('estudiante/catalogo', [
                'page_title' => 'Catálogo de Cursos',
                'cursos' => $cursosDisponibles
            ]);
        } catch (Exception $e) {
            error_log("Error en catálogo: " . $e->getMessage());
            $this->view('estudiante/catalogo', [
                'page_title' => 'Catálogo de Cursos',
                'error' => 'Error al cargar el catálogo'
            ]);
        }
    }

    /**
     * Muestra todos los cursos inscritos del estudiante.
     */
    public function misCursos(): void
    {
        try {
            require_once __DIR__ . '/../../config/database.php';
            
            $user_id = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("
                SELECT c.*, ic.fecha_inscripcion, ic.progreso
                FROM cursos c 
                INNER JOIN inscripciones_cursos ic ON c.id = ic.curso_id 
                WHERE ic.usuario_id = ? AND c.activo = 1
                ORDER BY ic.fecha_inscripcion DESC
            ");
            $stmt->execute([$user_id]);
            $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->view('estudiante/mis_cursos', [
                'page_title' => 'Mis Cursos',
                'cursos' => $cursos
            ]);
        } catch (Exception $e) {
            error_log("Error en mis cursos: " . $e->getMessage());
            $this->view('estudiante/mis_cursos', [
                'page_title' => 'Mis Cursos',
                'error' => 'Error al cargar los cursos'
            ]);
        }
    }

    /**
     * Muestra contenido del curso si el estudiante está inscrito.
     */
    public function verCurso(array $params): void
    {
        $curso_id = $params['id'] ?? null;
        
        if (!$curso_id) {
            $this->redirect('/estudiante/mis-cursos');
            return;
        }

        try {
            require_once __DIR__ . '/../../config/database.php';
            
            $user_id = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("
                SELECT c.*, ic.progreso 
                FROM cursos c 
                INNER JOIN inscripciones_cursos ic ON c.id = ic.curso_id 
                WHERE c.id = ? AND ic.usuario_id = ? AND c.activo = 1
            ");
            $stmt->execute([$curso_id, $user_id]);
            $curso = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$curso) {
                $this->redirect('/estudiante/mis-cursos');
                return;
            }

            
            $stmt = $pdo->prepare("
                SELECT * FROM modulos 
                WHERE curso_id = ? AND activo = 1 
                ORDER BY orden
            ");
            $stmt->execute([$curso_id]);
            $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->view('estudiante/curso_contenido', [
                'page_title' => $curso['titulo'],
                'curso' => $curso,
                'modulos' => $modulos
            ]);
        } catch (Exception $e) {
            error_log("Error al ver curso: " . $e->getMessage());
            $this->redirect('/estudiante/mis-cursos');
        }
    }

    /**
     * Redirige al detalle de resultado de evaluación.
     */
    public function resultadoEvaluacion(array $params): void
    {
        $evaluacion_id = $params['evaluacion_id'] ?? null;
        
        if (!$evaluacion_id) {
            $this->redirect('/estudiante/dashboard');
            return;
        }

        $this->redirect("/estudiante/resultado_evaluacion.php?evaluacion_id={$evaluacion_id}");
    }
}