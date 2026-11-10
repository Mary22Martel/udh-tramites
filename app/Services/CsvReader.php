<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Lee archivos CSV guardados desde Excel (con ; o , como separador,
 * en UTF-8 o ANSI/Windows-1252) y devuelve filas asociativas.
 */
class CsvReader
{
    /** @return list<array<string, string>> */
    public static function read(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        // Quitar BOM y corregir codificación de Excel en Windows
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw);
        $firstLine = $lines[0] ?? '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $header = null;
        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($data === [null] || count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if ($header === null) {
                $header = array_map(fn ($h) => Str::of($h)->ascii()->lower()->trim()->replace([' ', '-'], '_')->toString(), $data);

                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string) ($data[$i] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
