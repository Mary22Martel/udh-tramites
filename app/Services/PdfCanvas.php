<?php

namespace App\Services;

/**
 * Lienzo PDF de una página (A4) sin librerías externas: rectángulos, líneas,
 * texto con alineación y ajuste de línea, e imagen JPEG.
 * Las coordenadas se dan desde la esquina SUPERIOR izquierda (en puntos: 595 × 842).
 */
class PdfCanvas
{
    public const W = 595;

    public const H = 842;

    /** Anchos de Helvetica / Helvetica-Bold (1/1000 em) por código Windows-1252. */
    protected const WIDTHS = [
        'R' => [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,556,222,556,333,1000,556,556,333,1000,667,333,1000,556,611,556,556,222,222,333,333,350,556,1000,333,1000,500,333,944,556,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500],
        'B' => [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,556,278,556,500,1000,556,556,333,1000,667,333,1000,556,611,556,556,278,278,500,500,350,556,1000,333,1000,556,333,944,556,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556],
    ];

    protected string $ops = '';

    protected ?array $image = null;

    protected function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return sprintf('%.3F %.3F %.3F', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255);
    }

    protected function enc(string $s): string
    {
        return (string) mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    public function rect(float $x, float $y, float $w, float $h, string $fill): static
    {
        $this->ops .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $this->rgb($fill), $x, self::H - $y - $h, $w, $h);

        return $this;
    }

    /** Rectángulo con esquinas redondeadas. */
    public function roundRect(float $x, float $y, float $w, float $h, float $r, string $fill): static
    {
        $b = self::H - $y - $h;
        $k = 0.5523 * $r;
        $this->ops .= sprintf("%s rg\n", $this->rgb($fill))
            .sprintf("%.2F %.2F m %.2F %.2F l ", $x + $r, $b, $x + $w - $r, $b)
            .sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c ", $x + $w - $r + $k, $b, $x + $w, $b + $r - $k, $x + $w, $b + $r)
            .sprintf("%.2F %.2F l ", $x + $w, $b + $h - $r)
            .sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c ", $x + $w, $b + $h - $r + $k, $x + $w - $r + $k, $b + $h, $x + $w - $r, $b + $h)
            .sprintf("%.2F %.2F l ", $x + $r, $b + $h)
            .sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c ", $x + $r - $k, $b + $h, $x, $b + $h - $r + $k, $x, $b + $h - $r)
            .sprintf("%.2F %.2F l ", $x, $b + $r)
            .sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c f\n", $x, $b + $r - $k, $x + $r - $k, $b, $x + $r, $b);

        return $this;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = 'E2E8F0', float $width = 0.8): static
    {
        $this->ops .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $this->rgb($color), $width, $x1, self::H - $y1, $x2, self::H - $y2);

        return $this;
    }

    public function width(string $text, float $size, bool $bold = false): float
    {
        $w = 0;
        foreach (str_split($this->enc($text)) as $ch) {
            $w += self::WIDTHS[$bold ? 'B' : 'R'][ord($ch)] ?? 556;
        }

        return $w * $size / 1000;
    }

    /** Texto en una línea. $y es la línea base medida desde arriba. */
    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, string $color = '0F1E33', string $align = 'left'): static
    {
        if ($align !== 'left') {
            $w = $this->width($text, $size, $bold);
            $x -= $align === 'right' ? $w : $w / 2;
        }
        $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->enc($text));
        $this->ops .= sprintf("BT /%s %.1F Tf %s rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $this->rgb($color), $x, self::H - $y, $s);

        return $this;
    }

    /** Parte un texto en líneas que caben en $maxWidth. */
    public function wrap(string $text, float $size, float $maxWidth, bool $bold = false): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $try = $line === '' ? $word : "{$line} {$word}";
            if ($line !== '' && $this->width($try, $size, $bold) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    /** Imagen JPEG (una por documento). */
    public function jpeg(string $path, float $x, float $y, float $w, float $h): static
    {
        [$pw, $ph] = getimagesize($path);
        $this->image = ['data' => file_get_contents($path), 'w' => $pw, 'h' => $ph];
        $this->ops .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $w, $h, $x, self::H - $y - $h);

        return $this;
    }

    public function output(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >>'
                .($this->image ? ' /XObject << /Im1 7 0 R >>' : '').' >> >>',
            '<< /Length '.strlen($this->ops)." >>\nstream\n".$this->ops."endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        if ($this->image) {
            $objects[] = sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>', $this->image['w'], $this->image['h'], strlen($this->image['data']))
                ."\nstream\n".$this->image['data']."\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
