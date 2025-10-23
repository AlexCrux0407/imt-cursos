<?php

require_once __DIR__ . '/../Controller.php';

class MasterController extends Controller
{
    public function dashboard(): void
    {
        global $conn;
        
        $master_id = $_SESSION['user_id'];

        // Obtener estadísticas generales
        $stmt = $conn->prepare("\n            SELECT \n                COUNT(DISTINCT c.id) as total_cursos,\n                COUNT(DISTINCT u.id) as total_usuarios,\n                COUNT(DISTINCT i.id) as total_inscripciones,\n                AVG(COALESCE(i.progreso, 0)) as progreso_promedio\n            FROM cursos c\n            LEFT JOIN usuarios u ON u.role != 'master'\n            LEFT JOIN inscripciones i ON c.id = i.curso_id\n        ");
        $stmt->execute();
        $estadisticas = $stmt->fetch();

        // Obtener cursos recientes
        $stmt = $conn->prepare("\n            SELECT c.*, u.nombre as creador_nombre,\n                   COUNT(DISTINCT i.usuario_id) as total_inscritos\n            FROM cursos c\n            LEFT JOIN usuarios u ON c.creado_por = u.id\n            LEFT JOIN inscripciones i ON c.id = i.curso_id\n            GROUP BY c.id\n            ORDER BY c.created_at DESC\n            LIMIT 5\n        ");
        $stmt->execute();
        $cursosRecientes = $stmt->fetchAll();

        $this->view('master/dashboard', [
            'title' => 'Dashboard - Master',
            'estadisticas' => $estadisticas,
            'cursosRecientes' => $cursosRecientes
        ]);
    }

    public function adminCursos(): void
    {
        global $conn;

        // Obtener todos los cursos con información del creador
        $stmt = $conn->prepare("\n            SELECT c.*, u.nombre as creador_nombre,\n                   COUNT(DISTINCT i.usuario_id) as total_inscritos,\n                   AVG(COALESCE(i.progreso, 0)) as progreso_promedio\n            FROM cursos c\n            LEFT JOIN usuarios u ON c.creado_por = u.id\n            LEFT JOIN inscripciones i ON c.id = i.curso_id\n            GROUP BY c.id\n            ORDER BY c.created_at DESC\n        ");
        $stmt->execute();
        $cursos = $stmt->fetchAll();

        $this->view('master/admin_cursos', [
            'title' => 'Administrar Cursos - Master',
            'cursos' => $cursos
        ]);
    }

    public function asignarCursos(): void
    {
        global $conn;

        // Obtener todos los cursos activos
        $stmt = $conn->prepare("\n            SELECT c.*, u.nombre as creador_nombre\n            FROM cursos c\n            LEFT JOIN usuarios u ON c.creado_por = u.id\n            WHERE c.estado = 'activo'\n            ORDER BY c.titulo\n        ");
        $stmt->execute();
        $cursos = $stmt->fetchAll();

        // Obtener todos los estudiantes
        $stmt = $conn->prepare("\n            SELECT id, nombre, email\n            FROM usuarios\n            WHERE role = 'estudiante'\n            ORDER BY nombre\n        ");
        $stmt->execute();
        $estudiantes = $stmt->fetchAll();

        $this->view('master/asignar_cursos', [
            'title' => 'Asignar Cursos - Master',
            'cursos' => $cursos,
            'estudiantes' => $estudiantes
        ]);
    }

    public function procesarAsignacion(): void
    {
        global $conn;

        $curso_id = $_POST['curso_id'] ?? '';
        $estudiantes = $_POST['estudiantes'] ?? [];

        if (empty($curso_id) || empty($estudiantes)) {
            $this->redirect('/master/asignar-cursos?error=Debe seleccionar un curso y al menos un estudiante');
            return;
        }

        try {
            $conn->beginTransaction();

            foreach ($estudiantes as $estudiante_id) {
                // Verificar si ya está inscrito
                $stmt = $conn->prepare("\n                    SELECT id FROM inscripciones \n                    WHERE curso_id = :curso_id AND usuario_id = :usuario_id\n                ");
                $stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $estudiante_id]);
                
                if (!$stmt->fetch()) {
                    // Inscribir al estudiante
                    $stmt = $conn->prepare("\n                        INSERT INTO inscripciones (curso_id, usuario_id, fecha_inscripcion, estado, progreso)\n                        VALUES (:curso_id, :usuario_id, NOW(), 'activo', 0)\n                    ");
                    $stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $estudiante_id]);
                }
            }

            $conn->commit();
            $this->redirect('/master/asignar-cursos?success=Estudiantes asignados exitosamente');
        } catch (Exception $e) {
            $conn->rollBack();
            $this->redirect('/master/asignar-cursos?error=Error al asignar estudiantes');
        }
    }

    public function editarCurso(): void
    {
        global $conn;
        
        $curso_id = $this->getParam('id');

        if (!$curso_id) {
            $this->redirect('/master/admin-cursos?error=ID de curso requerido');
            return;
        }

        // Obtener información del curso
        $stmt = $conn->prepare("\n            SELECT c.*, u.nombre as creador_nombre\n            FROM cursos c\n            LEFT JOIN usuarios u ON c.creado_por = u.id\n            WHERE c.id = :id\n        ");
        $stmt->execute([':id' => $curso_id]);
        $curso = $stmt->fetch();

        if (!$curso) {
            $this->redirect('/master/admin-cursos?error=Curso no encontrado');
            return;
        }

        $this->view('master/editar_curso', [
            'title' => 'Editar Curso - Master',
            'curso' => $curso
        ]);
    }

    public function procesarCurso(): void
    {
        global $conn;
        
        $curso_id = $this->getParam('id');
        
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado = $_POST['estado'] ?? 'borrador';

        if (empty($titulo) || empty($descripcion)) {
            $redirect_url = $curso_id ? "/master/editar-curso/{$curso_id}" : "/master/admin-cursos";
            $this->redirect($redirect_url . '?error=Todos los campos son requeridos');
            return;
        }

        try {
            if ($curso_id) {
                // Actualizar curso existente
                $stmt = $conn->prepare("\n                    UPDATE cursos \n                    SET titulo = :titulo, descripcion = :descripcion, estado = :estado, updated_at = NOW()\n                    WHERE id = :id\n                ");
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':estado' => $estado,
                    ':id' => $curso_id
                ]);
                $message = 'Curso actualizado exitosamente';
            } else {
                // Crear nuevo curso (asignado al master)
                $stmt = $conn->prepare("\n                    INSERT INTO cursos (titulo, descripcion, estado, creado_por, created_at, updated_at)\n                    VALUES (:titulo, :descripcion, :estado, :master_id, NOW(), NOW())\n                ");
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':estado' => $estado,
                    ':master_id' => $_SESSION['user_id']
                ]);
                $message = 'Curso creado exitosamente';
            }

            $this->redirect('/master/admin-cursos?success=' . urlencode($message));
        } catch (Exception $e) {
            $this->redirect('/master/admin-cursos?error=Error al procesar el curso');
        }
    }
}