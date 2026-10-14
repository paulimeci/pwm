<?php

namespace App\Services\Admin;

use App\Models\Admin\LavazhoKryejOperacionet;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Kuponi i raportit ditor të Lavazhos.
 *
 * Trashëgon KuponLavazhoService, kështu që përdor të njëjtin printer (IP/port),
 * të njëjtat komanda ESC/POS dhe të njëjtën gjerësi letre.
 *
 * Përmbajtja:
 *  1. Sa mjete janë shërbyer gjithsej dhe sa për çdo kategori (SUV 6, Kamion 8, ...)
 *  2. Për çdo kategori: sa larje për çdo shërbim, sa lekë për secilin, totali i kategorisë
 *  3. Totali i përgjithshëm i ditës
 */
class KuponRaportLavazhoService extends KuponLavazhoService
{
    /**
     * =========================================================
     * PUBLIC API
     * =========================================================
     *
     * @param  Carbon|string  $data         Dita e raportit
     * @param  int|null       $operatoriId  Opsionale: raport vetëm për një operator
     */
    public function printoRaportin($data, ?int $operatoriId = null): bool
    {
        try {
            $content = $this->buildRaportContent(Carbon::parse($data), $operatoriId);
            return $this->sendToPrinter($content);
        } catch (\Throwable $e) {
            Log::error('KuponRaportLavazhoService error', [
                'data' => (string) $data,
                'operatori_id' => $operatoriId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // Për fallback-un Bluetooth (RawBT)
    public function buildRaportRaw($data, ?int $operatoriId = null): string
    {
        return $this->buildRaportContent(Carbon::parse($data), $operatoriId);
    }

    /**
     * =========================================================
     * TË DHËNAT
     * =========================================================
     * Numërohen mjetet e SHËRBYERA (status = larguar) me datë dalje = dita e zgjedhur,
     * njësoj si seksioni "Mjetet e Shërbyera". Nëse raporti yt i numëron ndryshe
     * (p.sh. sipas datës së hyrjes), ndryshoje vetëm këtë metodë.
     */
    protected function merrOperacionet(Carbon $data, ?int $operatoriId): Collection
    {
        return LavazhoKryejOperacionet::query()
            ->where('status', 'larguar')
            ->whereDate('ikja', $data->toDateString())
            ->when($operatoriId, fn ($q) => $q->where('id_operatori', $operatoriId))
            ->with(['sherbimi', 'kategoria', 'monedha', 'operatori'])
            ->get();
    }

    /**
     * Grupon: kategori -> shërbim (+ monedhë) -> numër & shumë.
     */
    protected function grupo(Collection $operacionet): array
    {
        $kategorite = [];
        $totaliNr = 0;
        $totaliShuma = [];
        $kodet = [];

        foreach ($operacionet as $op) {
            $katId   = $op->mjeti_kategoria_id ?? 0;
            $katEmri = $op->kategoria->kategoria ?? 'Pa kategori';
            $shId    = $op->id_operacionit ?? 0;
            $shEmri  = $op->sherbimi->sherbimi ?? 'Pa sherbim';
            $kodi    = $op->monedha->kodi ?? 'ALL';
            $vlera   = (float) $op->vlera;

            $kodet[$kodi] = true;

            if (!isset($kategorite[$katId])) {
                $kategorite[$katId] = [
                    'emri'      => $katEmri,
                    'nr'        => 0,
                    'sherbimet' => [],
                    'shuma'     => [],
                ];
            }

            $kategorite[$katId]['nr']++;
            $kategorite[$katId]['shuma'][$kodi] = ($kategorite[$katId]['shuma'][$kodi] ?? 0) + $vlera;

            $celesi = $shId . '|' . $kodi;

            if (!isset($kategorite[$katId]['sherbimet'][$celesi])) {
                $kategorite[$katId]['sherbimet'][$celesi] = [
                    'emri'  => $shEmri,
                    'kodi'  => $kodi,
                    'nr'    => 0,
                    'shuma' => 0.0,
                ];
            }

            $kategorite[$katId]['sherbimet'][$celesi]['nr']++;
            $kategorite[$katId]['sherbimet'][$celesi]['shuma'] += $vlera;

            $totaliNr++;
            $totaliShuma[$kodi] = ($totaliShuma[$kodi] ?? 0) + $vlera;
        }

        // Kategoritë dhe shërbimet në rend alfabetik
        uasort($kategorite, fn ($a, $b) => strcasecmp($a['emri'], $b['emri']));

        foreach ($kategorite as &$kategoria) {
            uasort($kategoria['sherbimet'], function ($a, $b) {
                return strcasecmp($a['emri'] . $a['kodi'], $b['emri'] . $b['kodi']);
            });
        }
        unset($kategoria);

        ksort($totaliShuma);

        return [
            'kategorite'    => $kategorite,
            'nr'            => $totaliNr,
            'shuma'         => $totaliShuma,
            'nr_monedhave'  => count($kodet),
        ];
    }

    /**
     * =========================================================
     * NDËRTIMI I KUPONIT
     * =========================================================
     */
    protected function buildRaportContent(Carbon $data, ?int $operatoriId): string
    {
        $operacionet = $this->merrOperacionet($data, $operatoriId);
        $g = $this->grupo($operacionet);

        // Kodi i monedhës shfaqet te rreshtat vetëm nëse ka më shumë se një monedhë
        $meKod = $g['nr_monedhave'] > 1;

        $emriOperatorit = $operatoriId ? ($operacionet->first()?->operatori?->name) : null;

        $content = '';
        $content .= $this->initPrinter();
        $content .= $this->alignCenter();

        // Titulli
        $content .= $this->textEmphasizedOn();
        $content .= $this->textDoubleHeightOn();
        $content .= $this->centerLine('LAVAZHO');
        $content .= $this->centerLine('RAPORT DITOR');
        $content .= $this->textDoubleHeightOff();
        $content .= $this->textEmphasizedOff();
        $content .= $this->separator('=');

        // Informacioni i raportit
        $content .= $this->alignLeft();
        $content .= $this->kvLine('Data', $data->format('d/m/Y'));
        if ($emriOperatorit) {
            $content .= $this->kvLine('Operatori', $emriOperatorit);
        }
        $content .= $this->kvLine('Printuar', now()->format('d/m H:i'));
        $content .= $this->separator('-');

        // Dita pa asnjë mjet të shërbyer
        if ($g['nr'] === 0) {
            $content .= $this->alignCenter();
            $content .= $this->centerLine('Nuk ka mjete te sherbyera');
            $content .= $this->centerLine('per kete date');
            $content .= "\n\n";
            $content .= $this->cutPaper();

            return $content;
        }

        // ── 1. PËRMBLEDHJA: mjete sipas kategorisë ──
        $content .= $this->alignCenter();
        $content .= $this->textEmphasizedOn();
        $content .= $this->centerLine('MJETE TE SHERBYERA');
        $content .= $this->textEmphasizedOff();
        $content .= $this->alignLeft();

        foreach ($g['kategorite'] as $kategoria) {
            $content .= $this->rresht2($kategoria['emri'], (string) $kategoria['nr']);
        }

        $content .= $this->separator('-');
        $content .= $this->textEmphasizedOn();
        $content .= $this->rresht2('TOTALI MJETE', (string) $g['nr']);
        $content .= $this->textEmphasizedOff();
        $content .= $this->separator('=');

        // ── 2. DETAJET: çdo kategori me shërbimet e saj ──
        foreach ($g['kategorite'] as $kategoria) {
            $content .= $this->textEmphasizedOn();
            $content .= $this->rresht2(
                $this->shkronjaTeMedha($kategoria['emri']),
                $kategoria['nr'] . ' mjete'
            );
            $content .= $this->textEmphasizedOff();

            foreach ($kategoria['sherbimet'] as $sherbimi) {
                $content .= $this->rresht3(
                    $sherbimi['emri'],
                    (string) $sherbimi['nr'],
                    $this->formatoShumen($sherbimi['shuma'], $sherbimi['kodi'], $meKod),
                    1
                );
            }

            $content .= $this->separator('-');

            ksort($kategoria['shuma']);
            foreach ($kategoria['shuma'] as $kodi => $shuma) {
                $content .= $this->rresht3(
                    'Totali',
                    '',
                    $this->formatoShumen($shuma, $kodi, true),
                    0
                );
            }

            $content .= $this->separator('=');
        }

        // ── 3. TOTALI I PËRGJITHSHËM I DITËS ──
        $content .= $this->alignCenter();
        $content .= $this->centerLine('TOTALI I DITES');
        $content .= $this->centerLine($g['nr'] . ' mjete te sherbyera');
        $content .= $this->textEmphasizedOn();
        $content .= $this->textDoubleHeightOn();

        foreach ($g['shuma'] as $kodi => $shuma) {
            $content .= $this->centerLine($this->formatoShumen($shuma, $kodi, true));
        }

        $content .= $this->textDoubleHeightOff();
        $content .= $this->textEmphasizedOff();

        $content .= "\n\n";
        $content .= $this->cutPaper();

        return $content;
    }

    /**
     * =========================================================
     * NDIHMËSE PËR FORMATIM
     * =========================================================
     */

    protected function formatoShumen(float $vlera, string $kodi, bool $meKod): string
    {
        return number_format($vlera, 2) . ($meKod ? ' ' . $kodi : '');
    }

    protected function shkronjaTeMedha(string $tekst): string
    {
        return strtoupper($this->normalizeText($tekst));
    }

    // Rresht me dy kolona: majtas + djathtas
    protected function rresht2(string $majtas, string $djathtas): string
    {
        $majtas   = $this->normalizeText($majtas);
        $djathtas = $this->normalizeText($djathtas);

        $gjeresiDjathtas = max(strlen($djathtas), 1);
        $gjeresiMajtas   = max($this->paperWidth - $gjeresiDjathtas - 1, 1);

        $majtas = $this->truncateText($majtas, $gjeresiMajtas);

        return sprintf("%-{$gjeresiMajtas}s %{$gjeresiDjathtas}s\n", $majtas, $djathtas);
    }

    // Rresht me tri kolona: emri | sasia | shuma (opsionalisht me hapësirë në fillim)
    protected function rresht3(string $majtas, string $mesi, string $djathtas, int $hapesira = 0): string
    {
        $gjeresiMesi     = 3;
        $gjeresiDjathtas = 12;
        $gjeresiMajtas   = max($this->paperWidth - $gjeresiMesi - $gjeresiDjathtas - 2 - $hapesira, 4);

        $majtas   = $this->truncateText($this->normalizeText($majtas), $gjeresiMajtas);
        $mesi     = $this->normalizeText($mesi);
        $djathtas = $this->normalizeText($djathtas);

        return str_repeat(' ', $hapesira)
            . sprintf("%-{$gjeresiMajtas}s %{$gjeresiMesi}s %{$gjeresiDjathtas}s\n", $majtas, $mesi, $djathtas);
    }
}
