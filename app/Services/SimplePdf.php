<?php

namespace App\Services;

/**
 * Generador mínimo de PDF (sin librerías externas).
 * Se usa para producir los documentos "generados por el sistema"
 * (oficios, resoluciones, constancias de demostración) cuando no se subió un archivo.
 */
class SimplePdf
{
    /** @var array<int, array{0:string,1:float,2:float,3:float,4:string}> */
    protected array $ops = [];

    protected float $y = 790;

    public function heading(string $text, float $size = 16): static
    {
        return $this->line($text, $size, 'B', true);
    }

    public function text(string $text, float $size = 11, bool $bold = false): static
    {
        foreach ($this->wrap($text, $size) as $line) {
            $this->line($line, $size, $bold ? 'B' : 'R');
        }

        return $this;
    }

    public function space(float $h = 10): static
    {
        $this->y -= $h;

        return $this;
    }

    public function rule(): static
    {
        $this->ops[] = ['RULE', 0, 0, $this->y + 4, ''];
        $this->y -= 10;

        return $this;
    }

    protected function line(string $text, float $size, string $font, bool $center = false): static
    {
        $x = 60;
        if ($center) {
            $x = max(60, (595 - $this->width($text, $size)) / 2);
        }
        $this->ops[] = [$font, $size, $x, $this->y, $text];
        $this->y -= $size * 1.45;

        return $this;
    }

    protected function width(string $text, float $size): float
    {
        return mb_strlen($text) * $size * 0.5;
    }

    /** @return list<string> */
    protected function wrap(string $text, float $size): array
    {
        $max = (int) floor(475 / ($size * 0.5));
        $out = [];
        foreach (preg_split('/\r?\n/', $text) as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/', $paragraph) as $word) {
                if (mb_strlen($line.' '.$word) > $max && $line !== '') {
                    $out[] = $line;
                    $line = $word;
                } else {
                    $line = trim($line.' '.$word);
                }
            }
            $out[] = $line;
        }

        return $out;
    }

    protected function esc(string $s): string
    {
        $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    public function output(): string
    {
        $stream = "0.18 0.72 0.62 RG 3 w 40 40 515 762 re S\n"; // marco verde UDH
        $stream .= "0.98 0.75 0.14 rg 40 770 515 32 re f\n";      // franja dorada
        foreach ($this->ops as [$font, $size, $x, $y, $text]) {
            if ($font === 'RULE') {
                $stream .= sprintf("0.6 0.6 0.6 RG 0.5 w 60 %.2F m 535 %.2F l S\n", $y, $y);

                continue;
            }
            $f = $font === 'B' ? 'F2' : 'F1';
            $stream .= sprintf("BT /%s %.1F Tf 0.1 0.12 0.2 rg %.2F %.2F Td (%s) Tj ET\n", $f, $size, $x, $y, $this->esc($text));
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
