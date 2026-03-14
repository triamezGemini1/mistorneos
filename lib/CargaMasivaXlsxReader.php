<?php
/**
 * Lectura mínima de .xlsx sin PhpSpreadsheet (ZIP + sheet XML).
 * Sirve para carga masiva cuando no hay vendor/composer en el servidor.
 */
declare(strict_types=1);

final class CargaMasivaXlsxReader
{
    /**
     * @return list<list<string>>
     */
    public static function leerHojas(string $path): array
    {
        if (!is_readable($path) || !class_exists('ZipArchive', false)) {
            return [];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $shared = self::cargarSharedStrings($zip);
        $sheetNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#i', (string)$n, $m)) {
                $sheetNames[(int)$m[1]] = $n;
            }
        }
        ksort($sheetNames);
        $mejor = [];
        $mejorN = 0;
        foreach ($sheetNames as $sheetFile) {
            $filas = self::leerSheetXml($zip->getFromName($sheetFile), $shared);
            $n = count($filas);
            if ($n > $mejorN) {
                $mejorN = $n;
                $mejor = $filas;
            }
        }
        $zip->close();
        return $mejor;
    }

    /**
     * @return list<string>
     */
    private static function cargarSharedStrings(ZipArchive $zip): array
    {
        $out = [];
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if ($raw === false || $raw === '') {
            return $out;
        }
        $sx = @simplexml_load_string($raw);
        if ($sx === false || !isset($sx->si)) {
            return $out;
        }
        foreach ($sx->si as $si) {
            if (isset($si->t)) {
                $out[] = (string)$si->t;
            } elseif (isset($si->r)) {
                $s = '';
                foreach ($si->r as $r) {
                    if (isset($r->t)) {
                        $s .= (string)$r->t;
                    }
                }
                $out[] = $s;
            } else {
                $out[] = '';
            }
        }
        return $out;
    }

    /**
     * @param list<string> $shared
     * @return list<list<string>>
     */
    /**
     * @param string|false $raw
     */
    private static function leerSheetXml($raw, array $shared): array
    {
        if ($raw === false || $raw === '') {
            return [];
        }
        $sx = @simplexml_load_string($raw);
        if ($sx === false) {
            return [];
        }
        $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $root = $sx->getDocNamespaces()[''] ?? '';
        $sheetData = $sx->sheetData ?? $sx->children($mainNs)->sheetData ?? null;
        if ($sheetData === null && $root !== '') {
            $sheetData = $sx->children($root)->sheetData ?? null;
        }
        if ($sheetData === null) {
            return [];
        }
        $filas = [];
        foreach ($sheetData->row as $row) {
            $porCol = [];
            $maxCol = -1;
            $cells = $row->c;
            if ($cells === null) {
                $cells = $row->children($mainNs)->c ?? [];
            }
            foreach ($cells as $c) {
                $r = (string)($c['r'] ?? '');
                if ($r === '' || !preg_match('/^([A-Z]+)(\d+)$/i', $r, $m)) {
                    continue;
                }
                $colIdx = self::letrasAColumna(strtoupper($m[1]));
                $maxCol = max($maxCol, $colIdx);
                $t = (string)($c['t'] ?? '');
                $v = isset($c->v) ? trim((string)$c->v) : '';
                if ($t === 's' && $v !== '' && ctype_digit($v)) {
                    $porCol[$colIdx] = $shared[(int)$v] ?? '';
                } elseif ($t === 'inlineStr' && isset($c->is->t)) {
                    $porCol[$colIdx] = (string)$c->is->t;
                } else {
                    $porCol[$colIdx] = $v;
                }
            }
            if ($maxCol < 0) {
                continue;
            }
            $linea = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $linea[] = (string)($porCol[$i] ?? '');
            }
            $filas[] = $linea;
        }
        return $filas;
    }

    private static function letrasAColumna(string $letters): int
    {
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }
}
