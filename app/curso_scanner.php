<?php
declare(strict_types=1);

/*
 Escáner de Curso Importado
 - Detecta CSS de tema y construye lista de lecciones.
 - Navega estructura módulo/tema/subtema/lección ordenada.
 - Extrae títulos desde <h1> y normaliza rutas relativas.
 */
function scanImportedCourse(string $cursoDir): array {
    $result = [
        'css' => null,
        'lecciones' => [],
        'modulos_detectados' => [],
        'temas_detectados' => [],
        'subtemas_detectados' => []
    ];

    $cssPath = $cursoDir . DIRECTORY_SEPARATOR . 'tema' . DIRECTORY_SEPARATOR . 'tema.css';
    if (is_file($cssPath)) {
        $result['css'] = 'tema/tema.css';
    }

    $contenidoBase = $cursoDir . DIRECTORY_SEPARATOR . 'contenido';
    if (!is_dir($contenidoBase)) {
        $items = @scandir($cursoDir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $candidate = $cursoDir . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . 'contenido';
                if (is_dir($candidate)) {
                    $contenidoBase = $candidate;
                    break;
                }
            }
        }
    }
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

    $toRelative = function (string $path) use ($cursoDir): string {
        $relative = str_replace($cursoDir . DIRECTORY_SEPARATOR, '', $path);
        return str_replace('\\', '/', $relative);
    };

    foreach ($safeList($contenidoBase) as $moduloDirName) {
        $moduloPath = $contenidoBase . DIRECTORY_SEPARATOR . $moduloDirName;
        if (!is_dir($moduloPath)) continue;
        $mOrden = $numFrom($moduloDirName, 'modulo');
        if ($mOrden === null) continue;

        $moduloIndexPath = $moduloPath . DIRECTORY_SEPARATOR . 'index.html';
        $result['modulos_detectados'][] = [
            'modulo_orden' => $mOrden,
            'dir' => $moduloDirName,
            'index_path' => is_file($moduloIndexPath) ? $toRelative($moduloIndexPath) : null
        ];

        foreach ($safeList($moduloPath) as $temaDirName) {
            $temaPath = $moduloPath . DIRECTORY_SEPARATOR . $temaDirName;
            if (!is_dir($temaPath)) continue;
            $tOrden = $numFrom($temaDirName, 'tema');
            if ($tOrden === null) continue;

            $temaIndexPath = $temaPath . DIRECTORY_SEPARATOR . 'index.html';
            $result['temas_detectados'][] = [
                'modulo_orden' => $mOrden,
                'tema_orden' => $tOrden,
                'dir' => $temaDirName,
                'index_path' => is_file($temaIndexPath) ? $toRelative($temaIndexPath) : null
            ];

            foreach ($safeList($temaPath) as $subtemaDirName) {
                $subtemaPath = $temaPath . DIRECTORY_SEPARATOR . $subtemaDirName;
                if (!is_dir($subtemaPath)) continue;
                $sOrden = $numFrom($subtemaDirName, 'subtema');
                if ($sOrden === null) continue;

                $subtemaIndexPath = $subtemaPath . DIRECTORY_SEPARATOR . 'index.html';
                $result['subtemas_detectados'][] = [
                    'modulo_orden' => $mOrden,
                    'tema_orden' => $tOrden,
                    'subtema_orden' => $sOrden,
                    'dir' => $subtemaDirName,
                    'index_path' => is_file($subtemaIndexPath) ? $toRelative($subtemaIndexPath) : null
                ];

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

                    $relative = $toRelative($filePath);

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

    usort($result['modulos_detectados'], function($a, $b) {
        return [$a['modulo_orden']] <=> [$b['modulo_orden']];
    });
    usort($result['temas_detectados'], function($a, $b) {
        return [$a['modulo_orden'], $a['tema_orden']] <=> [$b['modulo_orden'], $b['tema_orden']];
    });
    usort($result['subtemas_detectados'], function($a, $b) {
        return [$a['modulo_orden'], $a['tema_orden'], $a['subtema_orden']]
             <=> [$b['modulo_orden'], $b['tema_orden'], $b['subtema_orden']];
    });

    return $result;
}
