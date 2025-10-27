<?php

require_once __DIR__ . '/../Controller.php';

/*
 Controlador Ejecutivo
 Analiza y reporta métricas generales del sistema.
*/

class EjecutivoController extends Controller
{
    /**
     * Muestra estadísticas, gráficos y cursos populares.
     */
    public function dashboard(): void
    {
        global $conn;
        
        $ejecutivo_id = $_SESSION['user_id'];

        $stmt = $conn->prepare(
            "
            SELECT 
                COUNT(DISTINCT c.id) as total_cursos,
                COUNT(DISTINCT u.id) as total_usuarios,
                COUNT(DISTINCT i.id) as total_inscripciones,
                AVG(COALESCE(i.progreso, 0)) as progreso_promedio,
                COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.id END) as cursos_completados
            FROM cursos c
            LEFT JOIN usuarios u ON u.role IN ('estudiante', 'docente')
            LEFT JOIN inscripciones i ON c.id = i.curso_id
        ");
        $stmt->execute();
        $estadisticas = $stmt->fetch();

        $stmt = $conn->prepare(
            "
            SELECT 
                DATE_FORMAT(i.fecha_inscripcion, '%Y-%m') as mes,
                COUNT(*) as inscripciones
            FROM inscripciones i
            WHERE i.fecha_inscripcion >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(i.fecha_inscripcion, '%Y-%m')
            ORDER BY mes
        ");
        $stmt->execute();
        $datosGraficos = $stmt->fetchAll();

        $stmt = $conn->prepare(
            "
            SELECT c.titulo, COUNT(i.id) as total_inscripciones
            FROM cursos c
            LEFT JOIN inscripciones i ON c.id = i.curso_id
            GROUP BY c.id, c.titulo
            ORDER BY total_inscripciones DESC
            LIMIT 5
        ");
        $stmt->execute();
        $cursosPopulares = $stmt->fetchAll();

        $this->view('ejecutivo/dashboard', [
            'title' => 'Dashboard - Ejecutivo',
            'estadisticas' => $estadisticas,
            'datosGraficos' => $datosGraficos,
            'cursosPopulares' => $cursosPopulares
        ]);
    }

    /**
     * Genera reportes por período y tipo (inscripciones/usuarios/general).
     */
    public function reportes(): void
    {
        global $conn;

        $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
        $tipo_reporte = $_GET['tipo'] ?? 'general';

        $reporteData = [];

        switch ($tipo_reporte) {
            case 'inscripciones':
                $stmt = $conn->prepare(
                    "
                    SELECT 
                        c.titulo,
                        COUNT(i.id) as total_inscripciones,
                        COUNT(CASE WHEN i.estado = 'completado' THEN 1 END) as completados,
                        AVG(i.progreso) as progreso_promedio
                    FROM cursos c
                    LEFT JOIN inscripciones i ON c.id = i.curso_id
                    WHERE i.fecha_inscripcion BETWEEN :fecha_inicio AND :fecha_fin
                    GROUP BY c.id, c.titulo
                    ORDER BY total_inscripciones DESC
                ");
                break;

            case 'usuarios':
                $stmt = $conn->prepare(
                    "
                    SELECT 
                        u.role,
                        COUNT(*) as total,
                        COUNT(CASE WHEN u.fecha_registro BETWEEN :fecha_inicio AND :fecha_fin THEN 1 END) as nuevos
                    FROM usuarios u
                    GROUP BY u.role
                ");
                break;

            default: // general
                $stmt = $conn->prepare(
                    "
                    SELECT 
                        'Cursos Activos' as metrica,
                        COUNT(CASE WHEN c.estado = 'activo' THEN 1 END) as valor
                    FROM cursos c
                    UNION ALL
                    SELECT 
                        'Inscripciones Período' as metrica,
                        COUNT(*) as valor
                    FROM inscripciones i
                    WHERE i.fecha_inscripcion BETWEEN :fecha_inicio AND :fecha_fin
                    UNION ALL
                    SELECT 
                        'Usuarios Registrados' as metrica,
                        COUNT(*) as valor
                    FROM usuarios u
                    WHERE u.fecha_registro BETWEEN :fecha_inicio AND :fecha_fin
                ");
                break;
        }

        $stmt->execute([
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        $reporteData = $stmt->fetchAll();

        $this->view('ejecutivo/reportes', [
            'title' => 'Reportes - Ejecutivo',
            'reporteData' => $reporteData,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'tipo_reporte' => $tipo_reporte
        ]);
    }

    /**
     * Visualiza tendencias de inscripciones y progreso por curso.
     */
    public function analytics(): void
    {
        global $conn;

        $stmt = $conn->prepare(
            "
            SELECT 
                DATE(i.fecha_inscripcion) as fecha,
                COUNT(*) as inscripciones_dia
            FROM inscripciones i
            WHERE i.fecha_inscripcion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(i.fecha_inscripcion)
            ORDER BY fecha
        ");
        $stmt->execute();
        $inscripcionesDiarias = $stmt->fetchAll();

        $stmt = $conn->prepare(
            "
            SELECT 
                c.titulo,
                AVG(i.progreso) as progreso_promedio,
                COUNT(i.id) as total_estudiantes
            FROM cursos c
            LEFT JOIN inscripciones i ON c.id = i.curso_id
            WHERE c.estado = 'activo'
            GROUP BY c.id, c.titulo
            ORDER BY progreso_promedio DESC
        ");
        $stmt->execute();
        $progresoCursos = $stmt->fetchAll();

        $this->view('ejecutivo/analytics', [
            'title' => 'Analytics - Ejecutivo',
            'inscripcionesDiarias' => $inscripcionesDiarias,
            'progresoCursos' => $progresoCursos
        ]);
    }

    /**
     * Exporta reportes en formato CSV según el tipo.
     */
    public function exportarReporte(): void
    {
        global $conn;

        $tipo = $_GET['tipo'] ?? 'general';
        $formato = $_GET['formato'] ?? 'csv';

        switch ($tipo) {
            case 'inscripciones':
                $stmt = $conn->prepare(
                    "
                    SELECT 
                        c.titulo as 'Curso',
                        u.nombre as 'Estudiante',
                        u.email as 'Email',
                        i.fecha_inscripcion as 'Fecha Inscripción',
                        i.progreso as 'Progreso (%)',
                        i.estado as 'Estado'
                    FROM inscripciones i
                    JOIN cursos c ON i.curso_id = c.id
                    JOIN usuarios u ON i.usuario_id = u.id
                    ORDER BY i.fecha_inscripcion DESC
                ");
                $filename = 'reporte_inscripciones_' . date('Y-m-d');
                break;

            case 'cursos':
                $stmt = $conn->prepare(
                    "
                    SELECT 
                        c.titulo as 'Título',
                        c.descripcion as 'Descripción',
                        c.estado as 'Estado',
                        u.nombre as 'Creador',
                        c.fecha_creacion as 'Fecha Creación',
                        COUNT(i.id) as 'Total Inscritos'
                    FROM cursos c
                    LEFT JOIN usuarios u ON c.creado_por = u.id
                    LEFT JOIN inscripciones i ON c.id = i.curso_id
                    GROUP BY c.id
                    ORDER BY c.fecha_creacion DESC
                ");
                $filename = 'reporte_cursos_' . date('Y-m-d');
                break;

            default:
                $this->redirect('/ejecutivo/reportes?error=Tipo de reporte no válido');
                return;
        }

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($formato === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
            }
            
            fclose($output);
        } else {
            $this->redirect('/ejecutivo/reportes?error=Formato no soportado');
        }
    }
}