<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

// Verificar si TCPDF está disponible, si no, usar una implementación básica
$tcpdf_available = class_exists('TCPDF');

if (!$tcpdf_available) {
    // Implementación básica sin TCPDF
    $tipo = $_GET['tipo'] ?? '';
    $id = $_GET['id'] ?? '';
    
    // Configurar headers para PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="reporte_' . $tipo . '_' . date('Y-m-d') . '.pdf"');
    
    // Generar contenido básico (esto sería reemplazado por TCPDF en producción)
    echo "%PDF-1.4\n";
    echo "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    echo "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    echo "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    echo "4 0 obj\n<< /Length 44 >>\nstream\nBT /F1 12 Tf 100 700 Td (Reporte " . ucfirst($tipo) . ") Tj ET\nendstream\nendobj\n";
    echo "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    echo "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000244 00000 n \n0000000338 00000 n \n";
    echo "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n408\n%%EOF";
    exit;
}

// Si TCPDF está disponible, usar implementación completa
require_once __DIR__ . '/../../vendor/tcpdf/tcpdf.php';

$tipo = $_GET['tipo'] ?? '';
$id = $_GET['id'] ?? '';

if (!$tipo) {
    header('Location: ' . BASE_URL . '/ejecutivo/generar_reportes.php');
    exit;
}

// Crear nuevo PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar información del documento
$pdf->SetCreator('IMT Cursos');
$pdf->SetAuthor('Sistema Ejecutivo');
$pdf->SetTitle('Reporte ' . ucfirst($tipo));
$pdf->SetSubject('Reporte Ejecutivo');

// Configurar márgenes
$pdf->SetMargins(15, 27, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Configurar auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Configurar fuente
$pdf->SetFont('helvetica', '', 10);

// Función para header personalizado
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 15);
        $this->Cell(0, 15, 'IMT Cursos - Reporte Ejecutivo', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(10);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' - Generado el ' . date('d/m/Y H:i'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('IMT Cursos');
$pdf->SetAuthor('Sistema Ejecutivo');
$pdf->SetTitle('Reporte ' . ucfirst($tipo));

$pdf->SetMargins(15, 27, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 25);

// Generar contenido según el tipo de reporte
switch ($tipo) {
    case 'cursos':
        generarReporteCursos($pdf, $conn, $id);
        break;
    case 'estudiantes':
        generarReporteEstudiantes($pdf, $conn, $id);
        break;
    case 'curso':
        generarReporteCursoEspecifico($pdf, $conn, $id);
        break;
    case 'estudiante':
        generarReporteEstudianteEspecifico($pdf, $conn, $id);
        break;
    case 'resumen':
        generarReporteResumen($pdf, $conn);
        break;
    default:
        generarReporteGeneral($pdf, $conn);
}

// Generar nombre del archivo
$filename = 'reporte_' . $tipo . ($id ? '_' . $id : '') . '_' . date('Y-m-d_H-i') . '.pdf';

// Salida del PDF
$pdf->Output($filename, 'D');

// Funciones para generar diferentes tipos de reportes

function generarReporteCursos($pdf, $conn, $id = null) {
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte de Cursos', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Obtener datos
    $stmt = $conn->prepare("
        SELECT c.*, u.nombre as docente_nombre,
               COUNT(DISTINCT i.id) as total_inscritos,
               COUNT(DISTINCT CASE WHEN i.progreso = 100 THEN i.id END) as completados,
               AVG(COALESCE(i.progreso, 0)) as promedio_progreso,
               COUNT(DISTINCT m.id) as total_modulos
        FROM cursos c 
        LEFT JOIN usuarios u ON c.creado_por = u.id
        LEFT JOIN inscripciones i ON c.id = i.curso_id 
        LEFT JOIN modulos m ON c.id = m.curso_id
        WHERE c.estado != 'eliminado'
        GROUP BY c.id 
        ORDER BY c.titulo ASC
    ");
    $stmt->execute();
    $cursos = $stmt->fetchAll();
    
    // Estadísticas generales
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Estadísticas Generales', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $total_cursos = count($cursos);
    $total_inscritos = array_sum(array_column($cursos, 'total_inscritos'));
    $promedio_general = $total_inscritos > 0 ? array_sum(array_column($cursos, 'promedio_progreso')) / count($cursos) : 0;
    
    $pdf->Cell(45, 6, 'Total de Cursos:', 0, 0, 'L');
    $pdf->Cell(0, 6, $total_cursos, 0, 1, 'L');
    $pdf->Cell(45, 6, 'Total Inscripciones:', 0, 0, 'L');
    $pdf->Cell(0, 6, $total_inscritos, 0, 1, 'L');
    $pdf->Cell(45, 6, 'Progreso Promedio:', 0, 0, 'L');
    $pdf->Cell(0, 6, number_format($promedio_general, 1) . '%', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Tabla de cursos
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 8, 'Curso', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Docente', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Estudiantes', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Completados', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Progreso %', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 9);
    foreach ($cursos as $curso) {
        $pdf->Cell(60, 6, substr($curso['titulo'], 0, 25), 1, 0, 'L');
        $pdf->Cell(40, 6, substr($curso['docente_nombre'], 0, 20), 1, 0, 'L');
        $pdf->Cell(25, 6, $curso['total_inscritos'], 1, 0, 'C');
        $pdf->Cell(25, 6, $curso['completados'], 1, 0, 'C');
        $pdf->Cell(25, 6, number_format($curso['promedio_progreso'], 1) . '%', 1, 1, 'C');
    }
}

function generarReporteEstudiantes($pdf, $conn, $id = null) {
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte de Estudiantes', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Obtener datos
    $stmt = $conn->prepare("
        SELECT u.*, 
               COUNT(DISTINCT i.id) as total_cursos,
               COUNT(DISTINCT CASE WHEN i.progreso = 100 THEN i.id END) as cursos_completados,
               AVG(COALESCE(i.progreso, 0)) as promedio_progreso,
               AVG(CASE WHEN em.calificacion IS NOT NULL THEN em.calificacion ELSE NULL END) as promedio_calificaciones
        FROM usuarios u 
        LEFT JOIN inscripciones i ON u.id = i.usuario_id
        LEFT JOIN evaluaciones_modulo em ON u.id = em.usuario_id
        WHERE u.role = 'estudiante' AND u.estado = 'activo'
        GROUP BY u.id 
        ORDER BY u.nombre ASC
    ");
    $stmt->execute();
    $estudiantes = $stmt->fetchAll();
    
    // Estadísticas generales
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Estadísticas Generales', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $total_estudiantes = count($estudiantes);
    $total_cursos_inscritos = array_sum(array_column($estudiantes, 'total_cursos'));
    $promedio_general = $total_estudiantes > 0 ? array_sum(array_column($estudiantes, 'promedio_progreso')) / $total_estudiantes : 0;
    
    $pdf->Cell(45, 6, 'Total Estudiantes:', 0, 0, 'L');
    $pdf->Cell(0, 6, $total_estudiantes, 0, 1, 'L');
    $pdf->Cell(45, 6, 'Total Inscripciones:', 0, 0, 'L');
    $pdf->Cell(0, 6, $total_cursos_inscritos, 0, 1, 'L');
    $pdf->Cell(45, 6, 'Progreso Promedio:', 0, 0, 'L');
    $pdf->Cell(0, 6, number_format($promedio_general, 1) . '%', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Tabla de estudiantes
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 8, 'Estudiante', 1, 0, 'C');
    $pdf->Cell(60, 8, 'Email', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Cursos', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Completados', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Progreso %', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($estudiantes as $estudiante) {
        $pdf->Cell(50, 6, substr($estudiante['nombre'], 0, 25), 1, 0, 'L');
        $pdf->Cell(60, 6, substr($estudiante['email'], 0, 30), 1, 0, 'L');
        $pdf->Cell(20, 6, $estudiante['total_cursos'], 1, 0, 'C');
        $pdf->Cell(25, 6, $estudiante['cursos_completados'], 1, 0, 'C');
        $pdf->Cell(25, 6, number_format($estudiante['promedio_progreso'], 1) . '%', 1, 1, 'C');
    }
}

function generarReporteCursoEspecifico($pdf, $conn, $curso_id) {
    if (!$curso_id) return;
    
    $pdf->AddPage();
    
    // Obtener información del curso
    $stmt = $conn->prepare("
        SELECT c.*, u.nombre as docente_nombre
        FROM cursos c 
        LEFT JOIN usuarios u ON c.creado_por = u.id
        WHERE c.id = :curso_id
    ");
    $stmt->execute([':curso_id' => $curso_id]);
    $curso = $stmt->fetch();
    
    if (!$curso) return;
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte Detallado del Curso', 0, 1, 'C');
    $pdf->Ln(3);
    
    // Información del curso
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, $curso['titulo'], 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(30, 6, 'Docente:', 0, 0, 'L');
    $pdf->Cell(0, 6, $curso['docente_nombre'], 0, 1, 'L');
    $pdf->Cell(30, 6, 'Estado:', 0, 0, 'L');
    $pdf->Cell(0, 6, ucfirst($curso['estado']), 0, 1, 'L');
    $pdf->Cell(30, 6, 'Creado:', 0, 0, 'L');
    $pdf->Cell(0, 6, date('d/m/Y', strtotime($curso['created_at'])), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Descripción
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Descripción:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, $curso['descripcion'], 0, 'L');
    $pdf->Ln(5);
    
    // Estudiantes inscritos
    $stmt = $conn->prepare("
        SELECT i.*, u.nombre, u.email, i.progreso, i.fecha_inscripcion
        FROM inscripciones i
        INNER JOIN usuarios u ON i.usuario_id = u.id
        WHERE i.curso_id = :curso_id
        ORDER BY u.nombre ASC
    ");
    $stmt->execute([':curso_id' => $curso_id]);
    $estudiantes = $stmt->fetchAll();
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Estudiantes Inscritos (' . count($estudiantes) . ')', 0, 1, 'L');
    
    if (!empty($estudiantes)) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 6, 'Estudiante', 1, 0, 'C');
        $pdf->Cell(70, 6, 'Email', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Progreso %', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Inscripción', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 8);
        foreach ($estudiantes as $estudiante) {
            $pdf->Cell(60, 5, substr($estudiante['nombre'], 0, 30), 1, 0, 'L');
            $pdf->Cell(70, 5, substr($estudiante['email'], 0, 35), 1, 0, 'L');
            $pdf->Cell(25, 5, number_format($estudiante['progreso'], 1) . '%', 1, 0, 'C');
            $pdf->Cell(25, 5, date('d/m/Y', strtotime($estudiante['fecha_inscripcion'])), 1, 1, 'C');
        }
    }
}

function generarReporteEstudianteEspecifico($pdf, $conn, $estudiante_id) {
    if (!$estudiante_id) return;
    
    $pdf->AddPage();
    
    // Obtener información del estudiante
    $stmt = $conn->prepare("
        SELECT u.*
        FROM usuarios u 
        WHERE u.id = :estudiante_id AND u.role = 'estudiante'
    ");
    $stmt->execute([':estudiante_id' => $estudiante_id]);
    $estudiante = $stmt->fetch();
    
    if (!$estudiante) return;
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte Detallado del Estudiante', 0, 1, 'C');
    $pdf->Ln(3);
    
    // Información del estudiante
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, $estudiante['nombre'], 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(30, 6, 'Email:', 0, 0, 'L');
    $pdf->Cell(0, 6, $estudiante['email'], 0, 1, 'L');
    if ($estudiante['telefono']) {
        $pdf->Cell(30, 6, 'Teléfono:', 0, 0, 'L');
        $pdf->Cell(0, 6, $estudiante['telefono'], 0, 1, 'L');
    }
    $pdf->Cell(30, 6, 'Registro:', 0, 0, 'L');
    $pdf->Cell(0, 6, date('d/m/Y', strtotime($estudiante['created_at'])), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Cursos del estudiante
    $stmt = $conn->prepare("
        SELECT i.*, c.titulo, c.descripcion, i.progreso, i.fecha_inscripcion,
               u_docente.nombre as docente_nombre
        FROM inscripciones i
        INNER JOIN cursos c ON i.curso_id = c.id
        LEFT JOIN usuarios u_docente ON c.creado_por = u_docente.id
        WHERE i.usuario_id = :estudiante_id
        ORDER BY i.fecha_inscripcion DESC
    ");
    $stmt->execute([':estudiante_id' => $estudiante_id]);
    $cursos = $stmt->fetchAll();
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Cursos Inscritos (' . count($cursos) . ')', 0, 1, 'L');
    
    if (!empty($cursos)) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(70, 6, 'Curso', 1, 0, 'C');
        $pdf->Cell(40, 6, 'Docente', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Progreso %', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Inscripción', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 8);
        foreach ($cursos as $curso) {
            $pdf->Cell(70, 5, substr($curso['titulo'], 0, 35), 1, 0, 'L');
            $pdf->Cell(40, 5, substr($curso['docente_nombre'], 0, 20), 1, 0, 'L');
            $pdf->Cell(25, 5, number_format($curso['progreso'], 1) . '%', 1, 0, 'C');
            $pdf->Cell(25, 5, date('d/m/Y', strtotime($curso['fecha_inscripcion'])), 1, 1, 'C');
        }
    }
}

function generarReporteResumen($pdf, $conn) {
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte Ejecutivo General', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Estadísticas generales
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN u.role = 'estudiante' AND u.estado = 'activo' THEN u.id END) as total_estudiantes,
            COUNT(DISTINCT CASE WHEN u.role = 'docente' AND u.estado = 'activo' THEN u.id END) as total_docentes,
            COUNT(DISTINCT CASE WHEN c.estado = 'activo' THEN c.id END) as total_cursos,
            COUNT(DISTINCT i.id) as total_inscripciones,
            AVG(COALESCE(i.progreso, 0)) as promedio_progreso
        FROM usuarios u
        LEFT JOIN cursos c ON 1=1
        LEFT JOIN inscripciones i ON u.id = i.usuario_id
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Resumen Ejecutivo', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(50, 6, 'Total de Estudiantes:', 0, 0, 'L');
    $pdf->Cell(0, 6, number_format($stats['total_estudiantes']), 0, 1, 'L');
    $pdf->Cell(50, 6, 'Total de Docentes:', 0, 0, 'L');
    $pdf->Cell(0, 6, number_format($stats['total_docentes']), 0, 1, 'L');
    $pdf->Cell(50, 6, 'Total de Cursos:', 0, 0, 'L');
    $pdf->Cell(0, 6, number_format($stats['total_cursos']), 0, 1, 'L');
    $pdf->Cell(50, 6, 'Total Inscripciones:', 0, 0, 'L');
    $pdf->Cell(0, 6, number_format($stats['total_inscripciones']), 0, 1, 'L');
    $pdf->Cell(50, 6, 'Progreso Promedio:', 0, 0, 'L');
    $pdf->Cell(0, 6, number_format($stats['promedio_progreso'], 1) . '%', 0, 1, 'L');
    
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Reporte generado el: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
}

function generarReporteGeneral($pdf, $conn) {
    generarReporteResumen($pdf, $conn);
}
?>