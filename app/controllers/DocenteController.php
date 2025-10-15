<?php

require_once __DIR__ . '/../Controller.php';

class DocenteController extends Controller
{
    public function dashboard(): void
    {
        global $conn;
        
        $docente_id = $_SESSION['user_id'];

        // Obtener estadísticas del docente
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT CASE WHEN c.estado = 'activo' THEN c.id END) as cursos_activos,
                COUNT(DISTINCT i.usuario_id) as total_estudiantes,
                AVG(COALESCE(i.progreso, 0)) as promedio_avance,
                COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.id END) as certificados_emitidos
            FROM cursos c
            LEFT JOIN inscripciones i ON c.id = i.curso_id
            WHERE c.creado_por = :docente_id
        ");
        $stmt->execute([':docente_id' => $docente_id]);
        $estadisticas = $stmt->fetch();

        // Obtener cursos recientes del docente
        $stmt = $conn->prepare("
            SELECT c.*, 
                   COUNT(DISTINCT i.usuario_id) as total_inscritos,
                   AVG(COALESCE(i.progreso, 0)) as progreso_promedio
            FROM cursos c
            LEFT JOIN inscripciones i ON c.id = i.curso_id
            WHERE c.creado_por = :docente_id
            GROUP BY c.id
            ORDER BY c.fecha_creacion DESC
            LIMIT 5
        ");
        $stmt->execute([':docente_id' => $docente_id]);
        $cursosRecientes = $stmt->fetchAll();

        $this->view('docente/dashboard', [
            'title' => 'Dashboard - Docente',
            'estadisticas' => $estadisticas,
            'cursosRecientes' => $cursosRecientes
        ]);
    }

    public function adminCursos(): void
    {
        global $conn;
        
        $docente_id = $_SESSION['user_id'];

        // Obtener todos los cursos del docente
        $stmt = $conn->prepare("
            SELECT c.*, 
                   COUNT(DISTINCT i.usuario_id) as total_inscritos,
                   AVG(COALESCE(i.progreso, 0)) as progreso_promedio
            FROM cursos c
            LEFT JOIN inscripciones i ON c.id = i.curso_id
            WHERE c.creado_por = :docente_id
            GROUP BY c.id
            ORDER BY c.fecha_creacion DESC
        ");
        $stmt->execute([':docente_id' => $docente_id]);
        $cursos = $stmt->fetchAll();

        $this->view('docente/admin_cursos', [
            'title' => 'Administrar Cursos - Docente',
            'cursos' => $cursos
        ]);
    }

    public function editarCurso(): void
    {
        global $conn;
        
        $curso_id = $this->getParam('id');
        $docente_id = $_SESSION['user_id'];

        if (!$curso_id) {
            $this->redirect('/docente/admin-cursos?error=ID de curso requerido');
            return;
        }

        // Verificar que el curso pertenece al docente
        $stmt = $conn->prepare("SELECT * FROM cursos WHERE id = :id AND creado_por = :docente_id");
        $stmt->execute([':id' => $curso_id, ':docente_id' => $docente_id]);
        $curso = $stmt->fetch();

        if (!$curso) {
            $this->redirect('/docente/admin-cursos?error=Curso no encontrado');
            return;
        }

        $this->view('docente/editar_curso', [
            'title' => 'Editar Curso - Docente',
            'curso' => $curso
        ]);
    }

    public function procesarCurso(): void
    {
        global $conn;
        
        $docente_id = $_SESSION['user_id'];
        $curso_id = $this->getParam('id');
        
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado = $_POST['estado'] ?? 'borrador';

        if (empty($titulo) || empty($descripcion)) {
            $this->redirect('/docente/editar-curso/' . $curso_id . '?error=Todos los campos son requeridos');
            return;
        }

        try {
            if ($curso_id) {
                // Actualizar curso existente
                $stmt = $conn->prepare("
                    UPDATE cursos 
                    SET titulo = :titulo, descripcion = :descripcion, estado = :estado, fecha_actualizacion = NOW()
                    WHERE id = :id AND creado_por = :docente_id
                ");
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':estado' => $estado,
                    ':id' => $curso_id,
                    ':docente_id' => $docente_id
                ]);
                $message = 'Curso actualizado exitosamente';
            } else {
                // Crear nuevo curso
                $stmt = $conn->prepare("
                    INSERT INTO cursos (titulo, descripcion, estado, creado_por, fecha_creacion, fecha_actualizacion)
                    VALUES (:titulo, :descripcion, :estado, :docente_id, NOW(), NOW())
                ");
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':estado' => $estado,
                    ':docente_id' => $docente_id
                ]);
                $message = 'Curso creado exitosamente';
            }

            $this->redirect('/docente/admin-cursos?success=' . urlencode($message));
        } catch (Exception $e) {
            $this->redirect('/docente/admin-cursos?error=Error al procesar el curso');
        }
    }

    public function modulosCurso(): void
    {
        global $conn;
        
        $curso_id = $this->getParam('id');
        $docente_id = $_SESSION['user_id'];

        if (!$curso_id) {
            $this->redirect('/docente/admin-cursos?error=ID de curso requerido');
            return;
        }

        // Verificar que el curso pertenece al docente
        $stmt = $conn->prepare("SELECT * FROM cursos WHERE id = :id AND creado_por = :docente_id");
        $stmt->execute([':id' => $curso_id, ':docente_id' => $docente_id]);
        $curso = $stmt->fetch();

        if (!$curso) {
            $this->redirect('/docente/admin-cursos?error=Curso no encontrado');
            return;
        }

        // Obtener módulos del curso
        $stmt = $conn->prepare("
            SELECT m.*, COUNT(t.id) as total_temas
            FROM modulos m
            LEFT JOIN temas t ON m.id = t.modulo_id
            WHERE m.curso_id = :curso_id
            GROUP BY m.id
            ORDER BY m.orden ASC
        ");
        $stmt->execute([':curso_id' => $curso_id]);
        $modulos = $stmt->fetchAll();

        $this->view('docente/modulos_curso', [
            'title' => 'Módulos del Curso - Docente',
            'curso' => $curso,
            'modulos' => $modulos
        ]);
    }

    public function reportes(): void
    {
        global $conn;
        
        $docente_id = $_SESSION['user_id'];

        // Obtener estadísticas generales
        $stmt = $conn->prepare("
            SELECT 
                c.titulo,
                c.id as curso_id,
                COUNT(DISTINCT i.usuario_id) as total_estudiantes,
                AVG(COALESCE(i.progreso, 0)) as progreso_promedio,
                COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.id END) as completados
            FROM cursos c
            LEFT JOIN inscripciones i ON c.id = i.curso_id
            WHERE c.creado_por = :docente_id
            GROUP BY c.id
            ORDER BY c.titulo
        ");
        $stmt->execute([':docente_id' => $docente_id]);
        $reporteCursos = $stmt->fetchAll();

        $this->view('docente/reportes', [
            'title' => 'Reportes - Docente',
            'reporteCursos' => $reporteCursos
        ]);
    }
}