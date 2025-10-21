<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

// Verificar si PhpSpreadsheet está disponible, si no, usar una implementación básica
$phpspreadsheet_available = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');

if (!$phpspreadsheet_available) {
    // Implementación básica sin PhpSpreadsheet - generar CSV
    $tipo = $_GET['tipo'] ?? '';
    $id = $_GET['id'] ?? '';
    
    // Configurar headers para CSV (compatible con Excel)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . $tipo . '_' . date('Y-m-d') . '.csv"');
    
    // Crear output
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($tipo) {
        case 'cursos':
            generarCSVCursos($output, $conn, $id);
            break;
        case 'estudiantes':
            generarCSVEstudiantes($output, $conn, $id);
            break;
        case 'curso':
            generarCSVCursoEspecifico($output, $conn, $id);
            break;
        case 'estudiante':
            generarCSVEstudianteEspecifico($output, $conn, $id);
            break;
        case 'resumen':
            generarCSVResumen($output, $conn);
            break;
        default:
            generarCSVGeneral($output, $conn);
    }
    
    fclose($output);
    exit;
}

// Si PhpSpreadsheet está disponible, usar implementación completa
if ($phpspreadsheet_available) {
    // Intentar cargar PhpSpreadsheet desde diferentes ubicaciones posibles
    $autoload_paths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../vendor/phpspreadsheet/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php'
    ];
    
    $loaded = false;
    foreach ($autoload_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        $phpspreadsheet_available = false;
    }
}

$tipo = $_GET['tipo'] ?? '';
$id = $_GET['id'] ?? '';

if (!$tipo) {
    header('Location: ' . BASE_URL . '/ejecutivo/generar_reportes.php');
    exit;
}

// Si PhpSpreadsheet no está disponible, usar CSV
if (!$phpspreadsheet_available) {
    // Configurar headers para CSV (compatible con Excel)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . $tipo . '_' . date('Y-m-d') . '.csv"');
    
    // Crear output
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($tipo) {
        case 'cursos':
            generarCSVCursos($output, $conn, $id);
            break;
        case 'estudiantes':
            generarCSVEstudiantes($output, $conn, $id);
            break;
        case 'curso':
            generarCSVCursoEspecifico($output, $conn, $id);
            break;
        case 'estudiante':
            generarCSVEstudianteEspecifico($output, $conn, $id);
            break;
        case 'resumen':
            generarCSVResumen($output, $conn);
            break;
        default:
            generarCSVGeneral($output, $conn);
    }
    
    fclose($output);
    exit;
}

// Crear nuevo spreadsheet
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Configurar propiedades del documento
$spreadsheet->getProperties()
    ->setCreator('IMT Cursos')
    ->setLastModifiedBy('Sistema Ejecutivo')
    ->setTitle('Reporte ' . ucfirst($tipo))
    ->setSubject('Reporte Ejecutivo')
    ->setDescription('Reporte generado por el sistema ejecutivo de IMT Cursos');

// Generar contenido según el tipo de reporte
switch ($tipo) {
    case 'cursos':
        generarExcelCursos($sheet, $conn, $id);
        break;
    case 'estudiantes':
        generarExcelEstudiantes($sheet, $conn, $id);
        break;
    case 'curso':
        generarExcelCursoEspecifico($sheet, $conn, $id);
        break;
    case 'estudiante':
        generarExcelEstudianteEspecifico($sheet, $conn, $id);
        break;
    case 'resumen':
        generarExcelResumen($sheet, $conn);
        break;
    default:
        generarExcelGeneral($sheet, $conn);
}

// Generar nombre del archivo
$filename = 'reporte_' . $tipo . ($id ? '_' . $id : '') . '_' . date('Y-m-d_H-i') . '.xlsx';

// Configurar headers para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Crear writer y generar archivo
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');

// Funciones para generar diferentes tipos de reportes

function aplicarEstiloTitulo($sheet, $celda) {
    if (class_exists('PhpOffice\PhpSpreadsheet\Style\Alignment')) {
        $sheet->getStyle($celda)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle($celda)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($celda)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle($celda)->getFont()->getColor()->setRGB('FFFFFF');
    }
}

function aplicarEstiloEncabezado($sheet, $rango) {
    if (class_exists('PhpOffice\PhpSpreadsheet\Style\Alignment')) {
        $sheet->getStyle($rango)->getFont()->setBold(true);
        $sheet->getStyle($rango)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($rango)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('D9E2F3');
    }
}

function generarExcelCursos($sheet, $conn, $id = null) {
    // Título
    $sheet->setCellValue('A1', 'Reporte de Cursos - IMT Cursos');
    $sheet->mergeCells('A1:F1');
    aplicarEstiloTitulo($sheet, 'A1');
    
    $sheet->setCellValue('A2', 'Generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:F2');
    
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
    $row = 4;
    $sheet->setCellValue('A' . $row, 'Estadísticas Generales');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $total_cursos = count($cursos);
    $total_inscritos = array_sum(array_column($cursos, 'total_inscritos'));
    $promedio_general = $total_inscritos > 0 ? array_sum(array_column($cursos, 'promedio_progreso')) / count($cursos) : 0;
    
    $sheet->setCellValue('A' . $row, 'Total de Cursos:');
    $sheet->setCellValue('B' . $row, $total_cursos);
    $row++;
    $sheet->setCellValue('A' . $row, 'Total Inscripciones:');
    $sheet->setCellValue('B' . $row, $total_inscritos);
    $row++;
    $sheet->setCellValue('A' . $row, 'Progreso Promedio:');
    $sheet->setCellValue('B' . $row, number_format($promedio_general, 1) . '%');
    $row += 2;
    
    // Encabezados de tabla
    $sheet->setCellValue('A' . $row, 'Curso');
    $sheet->setCellValue('B' . $row, 'Docente');
    $sheet->setCellValue('C' . $row, 'Estado');
    $sheet->setCellValue('D' . $row, 'Estudiantes');
    $sheet->setCellValue('E' . $row, 'Completados');
    $sheet->setCellValue('F' . $row, 'Progreso %');
    $sheet->setCellValue('G' . $row, 'Módulos');
    $sheet->setCellValue('H' . $row, 'Fecha Creación');
    
    aplicarEstiloEncabezado($sheet, 'A' . $row . ':H' . $row);
    $row++;
    
    // Datos de cursos
    foreach ($cursos as $curso) {
        $sheet->setCellValue('A' . $row, $curso['titulo']);
        $sheet->setCellValue('B' . $row, $curso['docente_nombre']);
        $sheet->setCellValue('C' . $row, ucfirst($curso['estado']));
        $sheet->setCellValue('D' . $row, $curso['total_inscritos']);
        $sheet->setCellValue('E' . $row, $curso['completados']);
        $sheet->setCellValue('F' . $row, number_format($curso['promedio_progreso'], 1));
        $sheet->setCellValue('G' . $row, $curso['total_modulos']);
        $sheet->setCellValue('H' . $row, date('d/m/Y', strtotime($curso['created_at'])));
        $row++;
    }
    
    // Ajustar ancho de columnas
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

function generarExcelEstudiantes($sheet, $conn, $id = null) {
    // Título
    $sheet->setCellValue('A1', 'Reporte de Estudiantes - IMT Cursos');
    $sheet->mergeCells('A1:G1');
    aplicarEstiloTitulo($sheet, 'A1');
    
    $sheet->setCellValue('A2', 'Generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:G2');
    
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
    $row = 4;
    $sheet->setCellValue('A' . $row, 'Estadísticas Generales');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $total_estudiantes = count($estudiantes);
    $total_cursos_inscritos = array_sum(array_column($estudiantes, 'total_cursos'));
    $promedio_general = $total_estudiantes > 0 ? array_sum(array_column($estudiantes, 'promedio_progreso')) / $total_estudiantes : 0;
    
    $sheet->setCellValue('A' . $row, 'Total Estudiantes:');
    $sheet->setCellValue('B' . $row, $total_estudiantes);
    $row++;
    $sheet->setCellValue('A' . $row, 'Total Inscripciones:');
    $sheet->setCellValue('B' . $row, $total_cursos_inscritos);
    $row++;
    $sheet->setCellValue('A' . $row, 'Progreso Promedio:');
    $sheet->setCellValue('B' . $row, number_format($promedio_general, 1) . '%');
    $row += 2;
    
    // Encabezados de tabla
    $sheet->setCellValue('A' . $row, 'Estudiante');
    $sheet->setCellValue('B' . $row, 'Email');
    $sheet->setCellValue('C' . $row, 'Teléfono');
    $sheet->setCellValue('D' . $row, 'Cursos');
    $sheet->setCellValue('E' . $row, 'Completados');
    $sheet->setCellValue('F' . $row, 'Progreso %');
    $sheet->setCellValue('G' . $row, 'Promedio Calificaciones');
    $sheet->setCellValue('H' . $row, 'Fecha Registro');
    
    aplicarEstiloEncabezado($sheet, 'A' . $row . ':H' . $row);
    $row++;
    
    // Datos de estudiantes
    foreach ($estudiantes as $estudiante) {
        $sheet->setCellValue('A' . $row, $estudiante['nombre']);
        $sheet->setCellValue('B' . $row, $estudiante['email']);
        $sheet->setCellValue('C' . $row, $estudiante['telefono'] ?? '');
        $sheet->setCellValue('D' . $row, $estudiante['total_cursos']);
        $sheet->setCellValue('E' . $row, $estudiante['cursos_completados']);
        $sheet->setCellValue('F' . $row, number_format($estudiante['promedio_progreso'], 1));
        $sheet->setCellValue('G' . $row, $estudiante['promedio_calificaciones'] ? number_format($estudiante['promedio_calificaciones'], 1) : 'N/A');
        $sheet->setCellValue('H' . $row, date('d/m/Y', strtotime($estudiante['created_at'])));
        $row++;
    }
    
    // Ajustar ancho de columnas
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

function generarExcelCursoEspecifico($sheet, $conn, $curso_id) {
    if (!$curso_id) return;
    
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
    $sheet->setCellValue('A1', 'Reporte Detallado del Curso: ' . $curso['titulo']);
    $sheet->mergeCells('A1:F1');
    aplicarEstiloTitulo($sheet, 'A1');
    
    $sheet->setCellValue('A2', 'Generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:F2');
    
    // Información del curso
    $row = 4;
    $sheet->setCellValue('A' . $row, 'Información del Curso');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Título:');
    $sheet->setCellValue('B' . $row, $curso['titulo']);
    $row++;
    $sheet->setCellValue('A' . $row, 'Docente:');
    $sheet->setCellValue('B' . $row, $curso['docente_nombre']);
    $row++;
    $sheet->setCellValue('A' . $row, 'Estado:');
    $sheet->setCellValue('B' . $row, ucfirst($curso['estado']));
    $row++;
    $sheet->setCellValue('A' . $row, 'Fecha Creación:');
    $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($curso['created_at'])));
    $row++;
    $sheet->setCellValue('A' . $row, 'Descripción:');
    $sheet->setCellValue('B' . $row, $curso['descripcion']);
    $row += 2;
    
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
    
    $sheet->setCellValue('A' . $row, 'Estudiantes Inscritos (' . count($estudiantes) . ')');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    if (!empty($estudiantes)) {
        // Encabezados
        $sheet->setCellValue('A' . $row, 'Estudiante');
        $sheet->setCellValue('B' . $row, 'Email');
        $sheet->setCellValue('C' . $row, 'Progreso %');
        $sheet->setCellValue('D' . $row, 'Fecha Inscripción');
        $sheet->setCellValue('E' . $row, 'Estado');
        
        aplicarEstiloEncabezado($sheet, 'A' . $row . ':E' . $row);
        $row++;
        
        // Datos de estudiantes
        foreach ($estudiantes as $estudiante) {
            $sheet->setCellValue('A' . $row, $estudiante['nombre']);
            $sheet->setCellValue('B' . $row, $estudiante['email']);
            $sheet->setCellValue('C' . $row, number_format($estudiante['progreso'], 1));
            $sheet->setCellValue('D' . $row, date('d/m/Y', strtotime($estudiante['fecha_inscripcion'])));
            
            $estado = 'En progreso';
            if ($estudiante['progreso'] == 0) $estado = 'No iniciado';
            elseif ($estudiante['progreso'] == 100) $estado = 'Completado';
            
            $sheet->setCellValue('E' . $row, $estado);
            $row++;
        }
    }
    
    // Ajustar ancho de columnas
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

function generarExcelEstudianteEspecifico($sheet, $conn, $estudiante_id) {
    if (!$estudiante_id) return;
    
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
    $sheet->setCellValue('A1', 'Reporte Detallado del Estudiante: ' . $estudiante['nombre']);
    $sheet->mergeCells('A1:F1');
    aplicarEstiloTitulo($sheet, 'A1');
    
    $sheet->setCellValue('A2', 'Generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:F2');
    
    // Información del estudiante
    $row = 4;
    $sheet->setCellValue('A' . $row, 'Información del Estudiante');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Nombre:');
    $sheet->setCellValue('B' . $row, $estudiante['nombre']);
    $row++;
    $sheet->setCellValue('A' . $row, 'Email:');
    $sheet->setCellValue('B' . $row, $estudiante['email']);
    $row++;
    if ($estudiante['telefono']) {
        $sheet->setCellValue('A' . $row, 'Teléfono:');
        $sheet->setCellValue('B' . $row, $estudiante['telefono']);
        $row++;
    }
    $sheet->setCellValue('A' . $row, 'Fecha Registro:');
    $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($estudiante['created_at'])));
    $row += 2;
    
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
    
    $sheet->setCellValue('A' . $row, 'Cursos Inscritos (' . count($cursos) . ')');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    if (!empty($cursos)) {
        // Encabezados
        $sheet->setCellValue('A' . $row, 'Curso');
        $sheet->setCellValue('B' . $row, 'Docente');
        $sheet->setCellValue('C' . $row, 'Progreso %');
        $sheet->setCellValue('D' . $row, 'Fecha Inscripción');
        $sheet->setCellValue('E' . $row, 'Estado');
        
        aplicarEstiloEncabezado($sheet, 'A' . $row . ':E' . $row);
        $row++;
        
        // Datos de cursos
        foreach ($cursos as $curso) {
            $sheet->setCellValue('A' . $row, $curso['titulo']);
            $sheet->setCellValue('B' . $row, $curso['docente_nombre']);
            $sheet->setCellValue('C' . $row, number_format($curso['progreso'], 1));
            $sheet->setCellValue('D' . $row, date('d/m/Y', strtotime($curso['fecha_inscripcion'])));
            
            $estado = 'En progreso';
            if ($curso['progreso'] == 0) $estado = 'No iniciado';
            elseif ($curso['progreso'] == 100) $estado = 'Completado';
            
            $sheet->setCellValue('E' . $row, $estado);
            $row++;
        }
    }
    
    // Ajustar ancho de columnas
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

function generarExcelResumen($sheet, $conn) {
    // Título
    $sheet->setCellValue('A1', 'Reporte Ejecutivo General - IMT Cursos');
    $sheet->mergeCells('A1:D1');
    aplicarEstiloTitulo($sheet, 'A1');
    
    $sheet->setCellValue('A2', 'Generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:D2');
    
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
    
    $row = 4;
    $sheet->setCellValue('A' . $row, 'Resumen Ejecutivo');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Métrica');
    $sheet->setCellValue('B' . $row, 'Valor');
    aplicarEstiloEncabezado($sheet, 'A' . $row . ':B' . $row);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total de Estudiantes');
    $sheet->setCellValue('B' . $row, number_format($stats['total_estudiantes']));
    $row++;
    $sheet->setCellValue('A' . $row, 'Total de Docentes');
    $sheet->setCellValue('B' . $row, number_format($stats['total_docentes']));
    $row++;
    $sheet->setCellValue('A' . $row, 'Total de Cursos');
    $sheet->setCellValue('B' . $row, number_format($stats['total_cursos']));
    $row++;
    $sheet->setCellValue('A' . $row, 'Total Inscripciones');
    $sheet->setCellValue('B' . $row, number_format($stats['total_inscripciones']));
    $row++;
    $sheet->setCellValue('A' . $row, 'Progreso Promedio');
    $sheet->setCellValue('B' . $row, number_format($stats['promedio_progreso'], 1) . '%');
    
    // Ajustar ancho de columnas
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

function generarExcelGeneral($sheet, $conn) {
    generarExcelResumen($sheet, $conn);
}

// Funciones para CSV (fallback)

function generarCSVCursos($output, $conn, $id = null) {
    // Encabezados
    fputcsv($output, ['Curso', 'Docente', 'Estado', 'Estudiantes', 'Completados', 'Progreso %', 'Módulos', 'Fecha Creación']);
    
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
    
    foreach ($cursos as $curso) {
        fputcsv($output, [
            $curso['titulo'],
            $curso['docente_nombre'],
            ucfirst($curso['estado']),
            $curso['total_inscritos'],
            $curso['completados'],
            number_format($curso['promedio_progreso'], 1),
            $curso['total_modulos'],
            date('d/m/Y', strtotime($curso['created_at']))
        ]);
    }
}

function generarCSVEstudiantes($output, $conn, $id = null) {
    // Encabezados
    fputcsv($output, ['Estudiante', 'Email', 'Teléfono', 'Cursos', 'Completados', 'Progreso %', 'Promedio Calificaciones', 'Fecha Registro']);
    
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
    
    foreach ($estudiantes as $estudiante) {
        fputcsv($output, [
            $estudiante['nombre'],
            $estudiante['email'],
            $estudiante['telefono'] ?? '',
            $estudiante['total_cursos'],
            $estudiante['cursos_completados'],
            number_format($estudiante['promedio_progreso'], 1),
            $estudiante['promedio_calificaciones'] ? number_format($estudiante['promedio_calificaciones'], 1) : 'N/A',
            date('d/m/Y', strtotime($estudiante['created_at']))
        ]);
    }
}

function generarCSVCursoEspecifico($output, $conn, $curso_id) {
    if (!$curso_id) return;
    
    // Obtener información del curso
    $stmt = $conn->prepare("SELECT c.*, u.nombre as docente_nombre FROM cursos c LEFT JOIN usuarios u ON c.creado_por = u.id WHERE c.id = :curso_id");
    $stmt->execute([':curso_id' => $curso_id]);
    $curso = $stmt->fetch();
    
    if (!$curso) return;
    
    // Información del curso
    fputcsv($output, ['Información del Curso']);
    fputcsv($output, ['Título', $curso['titulo']]);
    fputcsv($output, ['Docente', $curso['docente_nombre']]);
    fputcsv($output, ['Estado', ucfirst($curso['estado'])]);
    fputcsv($output, ['Fecha Creación', date('d/m/Y', strtotime($curso['created_at']))]);
    fputcsv($output, []);
    
    // Estudiantes
    fputcsv($output, ['Estudiantes Inscritos']);
    fputcsv($output, ['Estudiante', 'Email', 'Progreso %', 'Fecha Inscripción', 'Estado']);
    
    $stmt = $conn->prepare("
        SELECT i.*, u.nombre, u.email, i.progreso, i.fecha_inscripcion
        FROM inscripciones i
        INNER JOIN usuarios u ON i.usuario_id = u.id
        WHERE i.curso_id = :curso_id
        ORDER BY u.nombre ASC
    ");
    $stmt->execute([':curso_id' => $curso_id]);
    $estudiantes = $stmt->fetchAll();
    
    foreach ($estudiantes as $estudiante) {
        $estado = 'En progreso';
        if ($estudiante['progreso'] == 0) $estado = 'No iniciado';
        elseif ($estudiante['progreso'] == 100) $estado = 'Completado';
        
        fputcsv($output, [
            $estudiante['nombre'],
            $estudiante['email'],
            number_format($estudiante['progreso'], 1),
            date('d/m/Y', strtotime($estudiante['fecha_inscripcion'])),
            $estado
        ]);
    }
}

function generarCSVEstudianteEspecifico($output, $conn, $estudiante_id) {
    if (!$estudiante_id) return;
    
    // Obtener información del estudiante
    $stmt = $conn->prepare("SELECT u.* FROM usuarios u WHERE u.id = :estudiante_id AND u.role = 'estudiante'");
    $stmt->execute([':estudiante_id' => $estudiante_id]);
    $estudiante = $stmt->fetch();
    
    if (!$estudiante) return;
    
    // Información del estudiante
    fputcsv($output, ['Información del Estudiante']);
    fputcsv($output, ['Nombre', $estudiante['nombre']]);
    fputcsv($output, ['Email', $estudiante['email']]);
    if ($estudiante['telefono']) fputcsv($output, ['Teléfono', $estudiante['telefono']]);
    fputcsv($output, ['Fecha Registro', date('d/m/Y', strtotime($estudiante['created_at']))]);
    fputcsv($output, []);
    
    // Cursos
    fputcsv($output, ['Cursos Inscritos']);
    fputcsv($output, ['Curso', 'Docente', 'Progreso %', 'Fecha Inscripción', 'Estado']);
    
    $stmt = $conn->prepare("
        SELECT i.*, c.titulo, i.progreso, i.fecha_inscripcion, u_docente.nombre as docente_nombre
        FROM inscripciones i
        INNER JOIN cursos c ON i.curso_id = c.id
        LEFT JOIN usuarios u_docente ON c.creado_por = u_docente.id
        WHERE i.usuario_id = :estudiante_id
        ORDER BY i.fecha_inscripcion DESC
    ");
    $stmt->execute([':estudiante_id' => $estudiante_id]);
    $cursos = $stmt->fetchAll();
    
    foreach ($cursos as $curso) {
        $estado = 'En progreso';
        if ($curso['progreso'] == 0) $estado = 'No iniciado';
        elseif ($curso['progreso'] == 100) $estado = 'Completado';
        
        fputcsv($output, [
            $curso['titulo'],
            $curso['docente_nombre'],
            number_format($curso['progreso'], 1),
            date('d/m/Y', strtotime($curso['fecha_inscripcion'])),
            $estado
        ]);
    }
}

function generarCSVResumen($output, $conn) {
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
    
    fputcsv($output, ['Reporte Ejecutivo General - IMT Cursos']);
    fputcsv($output, ['Generado el: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Métrica', 'Valor']);
    fputcsv($output, ['Total de Estudiantes', number_format($stats['total_estudiantes'])]);
    fputcsv($output, ['Total de Docentes', number_format($stats['total_docentes'])]);
    fputcsv($output, ['Total de Cursos', number_format($stats['total_cursos'])]);
    fputcsv($output, ['Total Inscripciones', number_format($stats['total_inscripciones'])]);
    fputcsv($output, ['Progreso Promedio', number_format($stats['promedio_progreso'], 1) . '%']);
}

function generarCSVGeneral($output, $conn) {
    generarCSVResumen($output, $conn);
}
?>