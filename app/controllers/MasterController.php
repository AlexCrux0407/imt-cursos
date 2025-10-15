<?php

require_once __DIR__ . '/../Controller.php';

class MasterController extends Controller
{
    public function dashboard(): void
    {
        global $conn;
        
        $master_id = $_SESSION['user_id'];

        // Obtener estadísticas generales
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_cursos,
                COUNT(DISTINCT u.id) as total_usuarios,
                COUNT(DISTINCT i.id) as total_inscripciones,
                AVG(COALESCE(i.progreso, 0)) as progreso_promedio
            FROM cursos c
            LEFT JOIN usuarios u ON u.role != 'master'
            LEFT JOIN inscripciones i ON c.id = i.curso_id
        ");
        $stmt->execute();
        $estadisticas = $stmt->fetch();

        // Obtener cursos recientes
        $stmt = $conn->prepare("
            SELECT c.*, u.nombre as creador_nombre,
                   COUNT(DISTINCT i.usuario_id) as total_inscritos
            FROM cursos c
            LEFT JOIN usuarios u ON c.creado_por = u.id
            LEFT JOIN inscripciones i ON c.id = i.curso_id
            GROUP BY c.id
            ORDER BY c.fecha_creacion DESC
            LIMIT 5
        ");
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
        $stmt = $conn->prepare("
            SELECT c.*, u.nombre as creador_nombre,
                   COUNT(DISTINCT i.usuario_id) as total_inscritos,
                   AVG(COALESCE(i.progreso, 0)) as progreso_promedio
            FROM cursos c
            LEFT JOIN usuarios u ON c.creado_por = u.id
            LEFT JOIN inscripciones i ON c.id = i.curso_id
            GROUP BY c.id
            ORDER BY c.fecha_creacion DESC
        ");
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
        $stmt = $conn->prepare("
            SELECT c.*, u.nombre as creador_nombre
            FROM cursos c
            LEFT JOIN usuarios u ON c.creado_por = u.id
            WHERE c.estado = 'activo'
            ORDER BY c.titulo
        ");
        $stmt->execute();
        $cursos = $stmt->fetchAll();

        // Obtener todos los estudiantes
        $stmt = $conn->prepare("
            SELECT id, nombre, email
            FROM usuarios
            WHERE role = 'estudiante'
            ORDER BY nombre
        ");
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
                $stmt = $conn->prepare("
                    SELECT id FROM inscripciones 
                    WHERE curso_id = :curso_id AND usuario_id = :usuario_id
                ");
                $stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $estudiante_id]);
                
                if (!$stmt->fetch()) {
                    // Inscribir al estudiante
                    $stmt = $conn->prepare("
                        INSERT INTO inscripciones (curso_id, usuario_id, fecha_inscripcion, estado, progreso)
                        VALUES (:curso_id, :usuario_id, NOW(), 'activo', 0)
                    ");
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
        $stmt = $conn->prepare("
            SELECT c.*, u.nombre as creador_nombre
            FROM cursos c
            LEFT JOIN usuarios u ON c.creado_por = u.id
            WHERE c.id = :id
        ");
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
                $stmt = $conn->prepare("
                    UPDATE cursos 
                    SET titulo = :titulo, descripcion = :descripcion, estado = :estado, fecha_actualizacion = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':estado' => $estado,
                    ':id' => $curso_id
                ]);
                $message = 'Curso actualizado exitosamente';
            } else {
                // Crear nuevo curso (asignado al master)
                $stmt = $conn->prepare("
                    INSERT INTO cursos (titulo, descripcion, estado, creado_por, fecha_creacion, fecha_actualizacion)
                    VALUES (:titulo, :descripcion, :estado, :master_id, NOW(), NOW())
                ");
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