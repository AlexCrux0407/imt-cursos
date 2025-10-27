<?php
declare(strict_types=1);

/**
 * Escanea un curso importado con convención de carpetas y retorna CSS opcional y lista de lecciones ordenadas.
 *
 * @param string $cursoDir Ruta física al curso
 * @return array{css:string|null, lecciones:array}
 */
function scanImportedCourse(string $cursoDir): array {
    $result = [
        'css' => null,
        'lecciones' => []
    ];

    $cssPath = $cursoDir . DIRECTORY_SEPARATOR . 'tema' . DIRECTORY_SEPARATOR . 'tema.css';
    if (is_file($cssPath)) {
        $result['css'] = 'tema/tema.css';
    }

    $contenidoBase = $cursoDir . DIRECTORY_SEPARATOR . 'contenido';
    if (!is_dir($contenidoBase)) {
        return $result;
    }

    $numFrom = function (string $name, string $prefix): ?int {
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

                    $lOrden = $numFrom($fileName, 'leccion');
                    if ($lOrden === null) $lOrden = 1;

                    $titulo = 'Lección ' . str_pad((string)$lOrden, 2, '0', STR_PAD_LEFT);
                    $html = @file_get_contents($filePath);
                    if ($html !== false && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
                        $t = trim(strip_tags($m[1]));
                        if ($t !== '') $titulo = $t;
                    }

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

    usort($result['lecciones'], function($a, $b) {
        return [$a['modulo_orden'], $a['tema_orden'], $a['subtema_orden'], $a['leccion_orden']]
             <=> [$b['modulo_orden'], $b['tema_orden'], $b['subtema_orden'], $b['leccion_orden']];
    });

    return $result;
}
