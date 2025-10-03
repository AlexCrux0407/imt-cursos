<?php
declare(strict_types=1);

/**
 * Escanea un directorio de curso importado usando convención:
 *  /tema/tema.css  (opcional)
 *  /contenido/modulo-XX/tema-YY/subtema-ZZ/leccion-NN.html
 *
 * @param string $cursoDir Ruta física al curso (p.ej. /var/www/imt-cursos/public/uploads/cursos/induccion-imt)
 * @return array{css:string|null, lecciones:array} css: ruta relativa del CSS si existe; lecciones: lista con orden y paths
 */
function scanImportedCourse(string $cursoDir): array {
    $result = [
        'css' => null,
        'lecciones' => [] // cada item: [modulo_orden, tema_orden, subtema_orden, leccion_orden, titulo, path_relativo]
    ];

    // 1) Detectar CSS opcional
    $cssPath = $cursoDir . DIRECTORY_SEPARATOR . 'tema' . DIRECTORY_SEPARATOR . 'tema.css';
    if (is_file($cssPath)) {
        $result['css'] = 'tema/tema.css';
    }

    // 2) Recorrer contenido
    $contenidoBase = $cursoDir . DIRECTORY_SEPARATOR . 'contenido';
    if (!is_dir($contenidoBase)) {
        return $result; // sin contenido
    }

    // Helpers para extraer números de "modulo-01", "tema-03", etc.
    $numFrom = function (string $name, string $prefix): ?int {
        // acepta modulo-01 o modulo_01 (guion o guion-bajo)
        if (preg_match('/^' . preg_quote($prefix, '/') . '[-_]?(\d+)/i', $name, $m)) {
            return (int)$m[1];
        }
        return null;
    };

    $safeList = function (string $dir): array {
        $items = @scandir($dir);
        if ($items === false) return [];
        return array_values(array_filter($items, fn($x) => $x !== '.' && $x !== '..'));
    };

    foreach ($safeList($contenidoBase) as $moduloDirName) {
        $moduloPath = $contenidoBase . DIRECTORY_SEPARATOR . $moduloDirName;
        if (!is_dir($moduloPath)) continue;
        $mOrden = $numFrom($moduloDirName, 'modulo');
        if ($mOrden === null) continue;

        foreach ($safeList($moduloPath) as $temaDirName) {
            $temaPath = $moduloPath . DIRECTORY_SEPARATOR . $temaDirName;
            if (!is_dir($temaPath)) continue;
            $tOrden = $numFrom($temaDirName, 'tema');
            if ($tOrden === null) continue;

            foreach ($safeList($temaPath) as $subtemaDirName) {
                $subtemaPath = $temaPath . DIRECTORY_SEPARATOR . $subtemaDirName;
                if (!is_dir($subtemaPath)) continue;
                $sOrden = $numFrom($subtemaDirName, 'subtema');
                if ($sOrden === null) continue;

                foreach ($safeList($subtemaPath) as $fileName) {
                    $filePath = $subtemaPath . DIRECTORY_SEPARATOR . $fileName;
                    if (!is_file($filePath)) continue;
                    if (!preg_match('/\.html?$/i', $fileName)) continue;

                    // leccion-XX.html -> XX
                    $lOrden = $numFrom($fileName, 'leccion');
                    if ($lOrden === null) $lOrden = 1;

                    // Intentar leer <h1> como título (opcional)
                    $titulo = 'Lección ' . str_pad((string)$lOrden, 2, '0', STR_PAD_LEFT);
                    $html = @file_get_contents($filePath);
                    if ($html !== false && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
                        // limpiar tags dentro del h1
                        $t = trim(strip_tags($m[1]));
                        if ($t !== '') $titulo = $t;
                    }

                    // ruta relativa desde la raíz del curso
                    $relative = str_replace($cursoDir . DIRECTORY_SEPARATOR, '', $filePath);
                    $relative = str_replace('\\', '/', $relative);

                    $result['lecciones'][] = [
                        'modulo_orden'  => $mOrden,
                        'tema_orden'    => $tOrden,
                        'subtema_orden' => $sOrden,
                        'leccion_orden' => $lOrden,
                        'titulo'        => $titulo,
                        'path'          => $relative,
                    ];
                }
            }
        }
    }

    // Ordenar todas las lecciones por (m, t, s, l)
    usort($result['lecciones'], function($a, $b) {
        return [$a['modulo_orden'], $a['tema_orden'], $a['subtema_orden'], $a['leccion_orden']]
             <=> [$b['modulo_orden'], $b['tema_orden'], $b['subtema_orden'], $b['leccion_orden']];
    });

    return $result;
}
