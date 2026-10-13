<?php

namespace App\Services\Admin;

use App\Models\Admin\LavazhoKryejOperacionet;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class KuponLavazhoService
{
    protected string $printerIp;
    protected int $printerPort;
    protected int $timeout = 5;

    protected int $paperWidth = 31;

    public function __construct(string $printerIp = '10.10.12.15', int $printerPort = 9100, ?int $paperWidth = null)
    {
        $this->printerIp = $printerIp;
        $this->printerPort = $printerPort;

        if ($paperWidth !== null && $paperWidth > 0) {
            $this->paperWidth = $paperWidth;
        }
    }

    /**
     * =========================================================
     * PUBLIC API
     * =========================================================
     */

    public function printoHyrjen(LavazhoKryejOperacionet $operacioni): bool
    {
        try {
            $content = $this->buildHyrjaContent($operacioni);
            return $this->sendToPrinter($content);
        } catch (\Throwable $e) {
            Log::error('KuponLavazhoService hyrja error', [
                'operacioni_id' => $operacioni->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function printoFaturen(LavazhoKryejOperacionet $operacioni, bool $eshteDalje): bool
    {
        try {
            $content = $this->buildFaturaContent($operacioni, $eshteDalje);
            return $this->sendToPrinter($content);
        } catch (\Throwable $e) {
            Log::error('KuponLavazhoService fatura error', [
                'operacioni_id' => $operacioni->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function buildHyrjaRaw(LavazhoKryejOperacionet $operacioni): string
    {
        return $this->buildHyrjaContent($operacioni);
    }

    public function buildFaturaRaw(LavazhoKryejOperacionet $operacioni, bool $eshteDalje): string
    {
        return $this->buildFaturaContent($operacioni, $eshteDalje);
    }

    public function sendRaw(string $content): bool
    {
        return $this->sendToPrinter($content);
    }

    /**
     * =========================================================
     * ZGJEDHJA E KUPONIT
     * - Hyrja (pa paguar)                  -> kuponi i HYRJES
     * - Fatura kur paguhet në regjistrim   -> kuponi i HYRJES (me statusin PAGUAR)
     * - Fatura kur mjeti largohet (dalje)  -> kuponi i DALJES
     * =========================================================
     */
    protected function buildHyrjaContent(LavazhoKryejOperacionet $operacioni): string
    {
        return $this->buildKuponHyrjeContent($operacioni);
    }

    protected function buildFaturaContent(LavazhoKryejOperacionet $operacioni, bool $eshteDalje): string
    {
        return $eshteDalje
            ? $this->buildKuponDaljeContent($operacioni)
            : $this->buildKuponHyrjeContent($operacioni);
    }

    /**
     * =========================================================
     * KUPONI I HYRJES
     * Fokusi: NUMRI RENDOR i madh, targa, ora e hyrjes
     * =========================================================
     */
    protected function buildKuponHyrjeContent(LavazhoKryejOperacionet $operacioni): string
    {
        $operacioni->loadMissing(['sherbimi', 'kategoria', 'monedha']);

        $paguar = $operacioni->pagesa === 'po';

        $content = '';
        $content .= $this->initPrinter();
        $content .= $this->alignCenter();

        // Titulli
        $content .= $this->textEmphasizedOn();
        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine('LAVAZHO');
        $content .= $this->centerLine('KUPON HYRJE');
        $content .= $this->textDoubleHeightOff();
        $content .= $this->textEmphasizedOff();
        $content .= $this->separator('=');

        // Numri rendor (shfaqet vetëm nëse ekziston)
        if ($operacioni->nr_rendor) {
            $content .= $this->centerLine('NR. RENDOR');
            $content .= $this->textEmphasizedOn();
            $content .= $this->textDoubleSizeOn();
            $content .= $this->centerLine(str_pad((string) $operacioni->nr_rendor, 3, '0', STR_PAD_LEFT));
            $content .= $this->textDoubleSizeOff();
            $content .= $this->textEmphasizedOff();
            $content .= $this->separator('-');
        }

        // Targa
        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine($this->normalizeText($operacioni->targa));
        $content .= $this->textDoubleHeightOff();
        $content .= $this->separator('-');

        // Detajet
        $content .= $this->alignLeft();
        $content .= $this->kvLine('Data', $operacioni->nisja->format('d/m/Y'));
        $content .= $this->kvLine('Ora hyrjes', $operacioni->nisja->format('H:i'));
        $content .= $this->kvLine('Kategoria', $operacioni->kategoria->kategoria ?? '-');
        $content .= $this->kvLine('Sherbimi', $operacioni->sherbimi->sherbimi ?? '-');
        $content .= $this->separator('-');

        // Çmimi + statusi
        $content .= $this->alignCenter();
        $content .= $this->textEmphasizedOn();
        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine('CMIMI: ' . number_format($operacioni->vlera, 2) . ' ' . ($operacioni->monedha->kodi ?? ''));
        $content .= $this->textDoubleHeightOff();
        $content .= $this->centerLine($paguar ? 'PAGUAR' : 'PA PAGUAR');
        $content .= $this->textEmphasizedOff();

        // Shënim në fund
        $content .= $this->separator('-');
        $content .= $this->centerLine('Ruani kete kupon');
        $content .= $this->centerLine('per marrjen e mjetit');

        $content .= "\n\n";
        $content .= $this->cutPaper();

        return $content;
    }

    /**
     * =========================================================
     * KUPONI I DALJES
     * Fokusi: hyrja, dalja, kohëzgjatja dhe TOTALI I PAGUAR
     * =========================================================
     */
    protected function buildKuponDaljeContent(LavazhoKryejOperacionet $operacioni): string
    {
        $operacioni->loadMissing(['sherbimi', 'kategoria', 'monedha']);

        $dalja = $operacioni->ikja ?? now();

        $content = '';
        $content .= $this->initPrinter();
        $content .= $this->alignCenter();

        // Titulli
        $content .= $this->textEmphasizedOn();
        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine('LAVAZHO');
        $content .= $this->centerLine('KUPON DALJE');
        $content .= $this->textDoubleHeightOff();
        $content .= $this->textEmphasizedOff();
        $content .= $this->separator('=');

        // Targa
        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine($this->normalizeText($operacioni->targa));
        $content .= $this->textDoubleHeightOff();
        $content .= $this->separator('-');

        // Detajet
        $content .= $this->alignLeft();
        $content .= $this->kvLine('Data', $dalja->format('d/m/Y'));
        $content .= $this->kvLine('Dalja', $dalja->format('H:i'));
        $content .= $this->kvLine('Kategoria', $operacioni->kategoria->kategoria ?? '-');
        $content .= $this->kvLine('Sherbimi', $operacioni->sherbimi->sherbimi ?? '-');
        $content .= $this->separator('=');

        // Totali
        $content .= $this->alignCenter();
        $content .= $this->centerLine('TOTALI I PAGUAR');
        $content .= $this->textEmphasizedOn();
        $content .= $this->textDoubleSizeOn();
        $content .= $this->centerLine(number_format($operacioni->vlera, 2));
        $content .= $this->textDoubleSizeOff();
        $content .= $this->centerLine($operacioni->monedha->kodi ?? '');
        $content .= $this->textEmphasizedOff();

        $content .= $this->separator('-');
        $content .= $this->centerLine('Faleminderit!');

        $content .= "\n\n";
        $content .= $this->cutPaper();

        return $content;
    }

    protected function formatoKohezgjatjen(CarbonInterface $nga, CarbonInterface $deri): string
    {
        $minuta = max((int) $nga->diffInMinutes($deri), 0);

        $dite = intdiv($minuta, 1440);
        $ore  = intdiv($minuta % 1440, 60);
        $min  = $minuta % 60;

        $pjeset = [];
        if ($dite > 0) { $pjeset[] = $dite . 'd'; }
        if ($ore > 0 || $dite > 0) { $pjeset[] = $ore . 'o'; }
        $pjeset[] = $min . 'min';

        return implode(' ', $pjeset);
    }

    protected function kvLine(string $label, string $value): string
    {
        $label = $this->normalizeText($label);
        $value = $this->normalizeText($value);

        $labelWidth = 11;
        $valueWidth = $this->paperWidth - $labelWidth - 1;

        $label = $this->truncateText($label, $labelWidth);
        $value = $this->truncateText($value, $valueWidth);

        return sprintf("%-{$labelWidth}s %{$valueWidth}s\n", $label . ':', $value);
    }

    protected function separator(string $char = '-'): string
    {
        return str_repeat($char, $this->paperWidth) . "\n";
    }

    protected function initPrinter(): string
    {
        return "\x1B\x40";
    }

    protected function alignLeft(): string
    {
        return "\x1B\x61\x00";
    }

    protected function alignCenter(): string
    {
        return "\x1B\x61\x01";
    }

    protected function textEmphasizedOn(): string
    {
        return "\x1B\x45\x01";
    }

    protected function textEmphasizedOff(): string
    {
        return "\x1B\x45\x00";
    }

    protected function textDoubleHeightOn(): string
    {
        return "\x1D\x21\x01";
    }

    protected function textDoubleHeightOff(): string
    {
        return "\x1D\x21\x00";
    }

    // Dyfishon edhe gjerësinë edhe lartësinë (p.sh. për numrin rendor dhe totalin)
    protected function textDoubleSizeOn(): string
    {
        return "\x1D\x21\x11";
    }

    protected function textDoubleSizeOff(): string
    {
        return "\x1D\x21\x00";
    }

    protected function centerLine(string $text): string
    {
        return $this->normalizeText($text) . "\n";
    }

    protected function truncateText(string $text, int $max): string
    {
        $text = trim($text);

        // Pa "…" (karakter shumëbyteshe që printeri e shfaq si simbole të çuditshme)
        return mb_strlen($text) > $max
            ? mb_substr($text, 0, $max)
            : $text;
    }

    protected function normalizeText(string $text): string
    {
        $text = trim($text);

        $map = [
            'ë' => 'e', 'Ë' => 'E',
            'ç' => 'c', 'Ç' => 'C',
        ];

        $text = strtr($text, $map);
        $text = preg_replace('/[^\x20-\x7E]/u', '', $text) ?? '';

        return $text;
    }

    protected function cutPaper(): string
    {
        return "\x1D\x56\x00";
    }

    /**
     * =========================================================
     * SEND TO PRINTER
     * =========================================================
     */
    protected function sendToPrinter(string $content): bool
    {
        try {
            Log::info('KuponLavazhoService sendToPrinter start', [
                'ip' => $this->printerIp,
                'port' => $this->printerPort,
                'bytes' => strlen($content),
            ]);

            $socket = @fsockopen(
                $this->printerIp,
                $this->printerPort,
                $errno,
                $errstr,
                $this->timeout
            );

            if (!$socket) {
                throw new \Exception("Nuk lidhet me printerin: {$errstr} ({$errno})");
            }

            stream_set_timeout($socket, $this->timeout);

            $bytesWritten = fwrite($socket, $content);

            $meta = stream_get_meta_data($socket);
            fclose($socket);

            Log::info('KuponLavazhoService sendToPrinter result', [
                'bytes_written' => $bytesWritten,
                'timed_out' => $meta['timed_out'] ?? false,
            ]);

            return $bytesWritten !== false && $bytesWritten > 0;
        } catch (\Throwable $e) {
            Log::error('KuponLavazhoService printer connection error', [
                'ip' => $this->printerIp,
                'port' => $this->printerPort,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
