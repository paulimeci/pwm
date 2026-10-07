<?php

namespace App\Services\Admin;

use App\Models\Admin\LavazhoKryejOperacionet;
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
     * KUPONI I HYRJES (pa vlerë — regjistrim i thjeshtë, pa paguar)
     * =========================================================
     */
    protected function buildHyrjaContent(LavazhoKryejOperacionet $operacioni): string
    {
        $operacioni->loadMissing(['sherbimi', 'kategoria']);

        $content = '';
        $content .= $this->initPrinter();
        $content .= $this->alignCenter();

        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine('LAVAZHO');
        $content .= $this->textDoubleHeightOff();
        $content .= $this->centerLine('Kuponi i Hyrjes');
        $content .= $this->separator('=');

        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine($this->normalizeText($operacioni->targa));
        $content .= $this->textDoubleHeightOff();
        $content .= $this->separator('-');

        $content .= $this->alignLeft();
        $content .= $this->kvLine('Data', $operacioni->nisja->format('d/m/Y'));
        $content .= $this->kvLine('Ora Hyrjes', $operacioni->nisja->format('H:i'));
        $content .= $this->kvLine('Kategoria', $operacioni->kategoria->kategoria ?? '-');
        $content .= $this->kvLine('Sherbimi', $operacioni->sherbimi->sherbimi ?? '-');
        $content .= $this->kvLine('Nr. Bilete', '#' . str_pad($operacioni->id, 6, '0', STR_PAD_LEFT));

        $content .= $this->separator('=');
        $content .= $this->alignCenter();
        $content .= $this->centerLine('Ju lutem ruajeni kete kupon');
        $content .= "\n\n";
        $content .= $this->cutPaper();

        return $content;
    }

    /**
     * =========================================================
     * KUPONI I FATURËS (parapagesë në regjistrim ose mbyllje/dalje reale)
     * =========================================================
     */
    protected function buildFaturaContent(LavazhoKryejOperacionet $operacioni, bool $eshteDalje): string
    {
        $operacioni->loadMissing(['sherbimi', 'kategoria', 'monedha']);

        $content = '';
        $content .= $this->initPrinter();
        $content .= $this->alignCenter();

        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine('LAVAZHO');
        $content .= $this->textDoubleHeightOff();

        if ($eshteDalje) {
            $content .= $this->centerLine('Kuponi i Daljes / Fature');
        } else {
            $content .= $this->centerLine('Kuponi i Pageses (Parapagese)');
        }

        $content .= $this->separator('=');

        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine($this->normalizeText($operacioni->targa));
        $content .= $this->textDoubleHeightOff();
        $content .= $this->separator('-');

        $content .= $this->alignLeft();
        $content .= $this->kvLine('Hyrja', $operacioni->nisja->format('d/m/Y H:i'));

        if ($eshteDalje && $operacioni->ikja) {
            $content .= $this->kvLine('Dalja', $operacioni->ikja->format('d/m/Y H:i'));
        } else {
            $content .= $this->kvLine('Statusi', 'PREZENT (parapaguar)');
        }

        $content .= $this->separator('-');
        $content .= $this->kvLine('Kategoria', $operacioni->kategoria->kategoria ?? '-');
        $content .= $this->kvLine('Sherbimi', $operacioni->sherbimi->sherbimi ?? '-');

        $content .= $this->separator('-');

        $content .= $this->alignCenter();
        $content .= $this->textEmphasizedOn();
        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine('TOTAL: ' . number_format($operacioni->vlera, 2) . ' ' . ($operacioni->monedha->kodi ?? ''));
        $content .= $this->textDoubleHeightOff();
        $content .= $this->textEmphasizedOff();

        $content .= $this->separator('=');
        $content .= $this->centerLine('Faleminderit!');
        $content .= "\n\n";
        $content .= $this->cutPaper();

        return $content;
    }

    /**
     * =========================================================
     * LOW LEVEL HELPERS
     * =========================================================
     */

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

    protected function centerLine(string $text): string
    {
        return $this->normalizeText($text) . "\n";
    }

    protected function truncateText(string $text, int $max): string
    {
        $text = trim($text);

        return mb_strlen($text) > $max
            ? mb_substr($text, 0, $max - 1) . '…'
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
