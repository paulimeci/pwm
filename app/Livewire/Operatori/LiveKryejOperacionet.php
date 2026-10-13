<?php

namespace App\Livewire\Operatori;

use App\Models\Admin\KategoriteEMjeteve;
use App\Models\Admin\OretCmimi;
use App\Models\Admin\TransaksioniOperacionit;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Admin\KategoriaPageses;
use App\Models\Admin\Monedhat;
use App\Models\Admin\Operacionet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use App\Services\Admin\KuponParkimiService;
use Illuminate\Support\Facades\DB;

class LiveKryejOperacionet extends Component
{
    use AuthorizesRequests;

    // Vetitë e formës
    public $targa;
    public $id_kategoria;
    public $id_kategoria_mjeti; // NEW: kategoria e mjetit në formën e regjistrimit
    public $id_monedha;
    public $eshte_paguar = false;
    public $shuma_paguar;
    public $kerkoTarge = '';
    public $klickedTarga = false;
    public $mjetiZgjedhur = null;
    public $koha_qendrimit = '';
    public $metoda_pageses = 'kesh';
    public $modal_id_kategoria;
    public $modal_id_monedha;
    public $modal_vlera = '';
    public $modal_id_fasha;
    public $transaksioniIRuajtur = null;

    // Dallon nëse modali u hap për "pagesë paraprake" (mjeti mbetet prezent)
    // apo për mbylljen reale të operacionit (mjeti largohet)
    public $eshteRegjistrimParaprak = false;
    public $modal_sasia = 1;

    public $tabiAktiv = 'sot'; // sot, dje, cakto_daten
    public $dataSpecifike;

    public $mjetiLarguarZgjedhur = null;
    public $shfaqModalDetajet = false;

    public $shfaqModalSkadimi = false;
    public $mjetiSkaduarZgjedhur = null;
    public $detajetSkadimit = [];
    public $vlera_shtese = 0;

    public $shtese_id_fasha = null;
    public $shtese_sasia = null;

    public $vlera_dite_plota = 0;
    public $shtese_sasia_plote = 0;
    public $shtese_id_kategoria_plote = null;
    public $shtese_id_fasha_plote = null;

    public $shtese_id_kategoria_gjysme = null;
    public $shtese_id_fasha_gjysme = null;

    public $mjetiId;
    public $edit_nisja;
    public $edit_id_kategoria;
    public $edit_id_kategoria_mjeti; // NEW
    public $edit_id_monedha;
    public $edit_id_fasha;
    public $edit_sasia = 1;
    public $edit_vlera = '';
    public $isEditModalOpen = false;
    public $detajetPeriudhaveFikse = [];
    public $shfaqDetajetShtese = false;

    public $shfaqModalFshirje = false;
    public $mjetiPerFshirje = null;
    public $tabiMjetetPrezent = 'all'; // all, parapagim, pa_paguar, skaduar
    public $faqjaPrezent = 1;


    public function mount()
    {
        $kategoriaDefault = KategoriaPageses::where('is_default', 1)->first();
        if ($kategoriaDefault) {
            $this->id_kategoria = $kategoriaDefault->id;
        }

        $monedhaDefault = Monedhat::where('kodi', 'ALL')->first();
        if ($monedhaDefault) {
            $this->id_monedha = $monedhaDefault->id;
        }

        $this->id_kategoria_mjeti = $this->idKategoriaMjetiDefault();
    }

    // ════════════════════════════════════════════════
    // HELPER-Ë PËR KATEGORINË E MJETIT
    // ════════════════════════════════════════════════

    private function idKategoriaMjetiDefault(): ?int
    {
        return KategoriteEMjeteve::where('is_default', true)->value('id');
    }

    // Kategoria e mjetit të operacionit; për operacione të vjetra pa kategori merr default-in
    private function idMjetiE(?Operacionet $operacioni): ?int
    {
        return $operacioni?->id_kategoria_mjeti ?: $this->idKategoriaMjetiDefault();
    }

    // Query bazë i fashave për një kategori pagese + kategori mjeti
    private function fashatEKategorise($idKategoria, ?int $idMjeti)
    {
        return OretCmimi::where('id_kategoria_rezervimit', $idKategoria)
            ->when($idMjeti, fn($q) => $q->where('id_kategoria_mjeti', $idMjeti));
    }

    public function updatedEshtePaguar($value)
    {
        if (!$value) {
            return; // nëse e çekon, nuk bëjmë asgjë
        }

        $this->validate([
            'targa' => 'required|string|max:20',
            'id_kategoria_mjeti' => 'required|exists:kategorite_e_mjeteve,id',
        ], [
            'targa.required' => 'Ju lutem vendosni targën e makinës para se ta shënoni si të paguar.',
            'id_kategoria_mjeti.required' => 'Zgjidhni kategorinë e mjetit.',
        ]);

        $targaPastruar = strtoupper(str_replace(' ', '', $this->targa));

        $ekziston = Operacionet::where('status', 'prezent')
            ->whereRaw("REPLACE(UPPER(targa), ' ', '') = ?", [$targaPastruar])
            ->exists();

        if ($ekziston) {
            $this->addError('targa', 'Kjo targë është tashmë e regjistruar si "Prezent" në parking.');
            $this->eshte_paguar = false;
            return;
        }

        // Regjistrojmë menjëherë mjetin si "prezent"
        $operacioni = Operacionet::create([
            'targa'              => $targaPastruar,
            'id_kategoria_mjeti' => $this->id_kategoria_mjeti,
            'id_operatori'       => Auth::user()->id,
            'nisja'              => now(),
            'ikja'               => null,
            'status'             => 'prezent',
        ]);

        // Pastrojmë formën e regjistrimit për mjetin tjetër
        $this->reset('targa', 'eshte_paguar', 'shuma_paguar');
        $this->id_kategoria_mjeti = $this->idKategoriaMjetiDefault();

        // Hapim modalin e pagesës në "modë paraprak" (pa e mbyllur operacionin)
        $this->eshteRegjistrimParaprak = true;
        $this->inicializoModalinPerOperacionin($operacioni);
        $this->dispatch('printo-kupon', url: route('print.hyrje', $operacioni->id));
        session()->flash('success', 'Mjeti u regjistrua si Prezent. Vazhdo me pagesën paraprake më poshtë.');
    }

    public function shfaqModalPagesen($id)
    {
        $operacioni = Operacionet::with(['kategoriaMjetit', 'transaksioni.prenotimi', 'transaksioni.fashaOrare', 'transaksioni.monedha'])->find($id);

        if (!$operacioni) {
            return;
        }

        $statusi = $this->statusiSkadimit($operacioni);

        // Nëse koha e paguar ka skaduar, hap modalin e skadimit në vend të atij normal
        if ($statusi['skaduar']) {
            $this->mjetiSkaduarZgjedhur = $operacioni;
            $this->detajetSkadimit = $statusi;
            $this->llogaritVlerenShtese($operacioni, $statusi);
            $this->shfaqModalSkadimi = true;
            return;
        }

        $this->eshteRegjistrimParaprak = false;
        $this->inicializoModalinPerOperacionin($operacioni);
    }

    private function inicializoModalinPerOperacionin(Operacionet $operacioni)
    {
        $this->mjetiZgjedhur = $operacioni;
        $idMjeti = $this->idMjetiE($operacioni);

        $transaksioniEkzistues = TransaksioniOperacionit::with('fashaOrare')
            ->where('id_operacionit', $operacioni->id)
            ->first();

        $this->transaksioniIRuajtur = $transaksioniEkzistues;

        $hyrja = Carbon::parse($operacioni->nisja);
        $tani  = Carbon::now();

        if ($this->eshteRegjistrimParaprak) {
            $sherbimiDefault = KategoriaPageses::where('is_default', 1)->first();
            $this->modal_id_kategoria = $sherbimiDefault ? $sherbimiDefault->id : null;

            $monedhaDefault = Monedhat::where('kodi', 'ALL')->first();
            $this->modal_id_monedha = $monedhaDefault ? $monedhaDefault->id : null;

            $fashaEPare = $this->fashatEKategorise($this->modal_id_kategoria, $idMjeti)
                ->orderBy('nga')
                ->first();
            $this->modal_id_fasha = $fashaEPare ? $fashaEPare->id : null;

            $this->llogaritCmiminAutomatik();
        } elseif ($transaksioniEkzistues) {
            $this->modal_id_kategoria = $transaksioniEkzistues->id_prenotimit;
            $this->modal_id_monedha   = $transaksioniEkzistues->monedha;
            $this->modal_id_fasha     = $transaksioniEkzistues->id_fashes_orare;
            $this->modal_sasia        = $transaksioniEkzistues->sasia ?? 1;
            $this->modal_vlera        = $transaksioniEkzistues->vlera;
        } else {
            $sherbimiDefault = KategoriaPageses::where('is_default', 1)->first();
            $this->modal_id_kategoria = $sherbimiDefault ? $sherbimiDefault->id : null;

            $monedhaDefault = Monedhat::where('kodi', 'ALL')->first();
            $this->modal_id_monedha = $monedhaDefault ? $monedhaDefault->id : null;

            if ($this->modal_id_kategoria) {
                $kategoriaZgjedhur = KategoriaPageses::find($this->modal_id_kategoria);

                if ($kategoriaZgjedhur && $kategoriaZgjedhur->eshteNjesiaDite()) {
                    // Kategori ditë — llogarisim sasinë automatike (ceil)
                    $njesiaCmimi = $this->fashatEKategorise($this->modal_id_kategoria, $idMjeti)->first();
                    $this->modal_id_fasha = $njesiaCmimi ? $njesiaCmimi->id : null;
                    $this->modal_sasia = $this->llogaritSasineAutomatike($this->modal_id_kategoria);
                } else {
                    // Orë
                    $fashaEPershtatshme = $this->gjejFashenPerKohenReale($this->modal_id_kategoria);
                    $this->modal_id_fasha = $fashaEPershtatshme ? $fashaEPershtatshme->id : null;
                }
            } else {
                $this->modal_id_fasha = null;
            }

            $this->llogaritCmiminAutomatik();
        }

        // Shfaqja e tekstit të kohës së qëndrimit
        $diferencaDite   = (int) $hyrja->diffInDays($tani);
        $diferencaOre    = (int) ($hyrja->diffInHours($tani) % 24);
        $diferencaMinuta = (int) ($hyrja->diffInMinutes($tani) % 60);

        $pjeset = [];
        if ($diferencaDite >= 1) { $pjeset[] = $diferencaDite . ' ditë'; }
        if ($diferencaOre > 0) { $pjeset[] = $diferencaOre . ' orë'; }
        if ($diferencaMinuta > 0 || empty($pjeset)) { $pjeset[] = $diferencaMinuta . ' min'; }

        if (count($pjeset) > 1) {
            $fundi = array_pop($pjeset);
            $this->koha_qendrimit = implode(', ', $pjeset) . ' e ' . $fundi;
        } else {
            $this->koha_qendrimit = $pjeset[0];
        }

        $this->klickedTarga = true;
    }

    // Gjen fashën orare që përputhet me kohën reale të qëndrimit (sipas kategorisë së mjetit)
    private function gjejFashenPerKohenReale(int $idKategoria): ?OretCmimi
    {
        if (!$this->mjetiZgjedhur) {
            return null;
        }

        $hyrja = Carbon::parse($this->mjetiZgjedhur->nisja);
        $tani  = Carbon::now();
        $oreTeQendrimitReal = max($hyrja->diffInMinutes($tani) / 60, 0.01);

        $fashatEKategorise = $this->fashatEKategorise($idKategoria, $this->idMjetiE($this->mjetiZgjedhur))
            ->orderBy('nga')
            ->get();

        $fashaAutodetektuar = $fashatEKategorise->first(function ($fasha) use ($oreTeQendrimitReal) {
            return $oreTeQendrimitReal >= $fasha->nga && $oreTeQendrimitReal <= $fasha->ne;
        });

        // Nëse ka kaluar limitin e fundit, merr fashën maksimale
        return $fashaAutodetektuar ?: $fashatEKategorise->last();
    }

    public function llogaritCmiminAutomatik()
    {
        if (!$this->mjetiZgjedhur) {
            return;
        }

        $idMjeti = $this->idMjetiE($this->mjetiZgjedhur);
        $kategoria = KategoriaPageses::find($this->modal_id_kategoria);
        $eshteNjesiaDite = $kategoria && $kategoria->eshteNjesiaDite();

        if ($eshteNjesiaDite) {
            // Llogaritje me SASI — çmimi_njësi × sasia
            $njesiaCmimi = $this->modal_id_fasha
                ? OretCmimi::find($this->modal_id_fasha)
                : $this->fashatEKategorise($this->modal_id_kategoria, $idMjeti)->first();

            $cmimiMonedhes = $njesiaCmimi
                ? $njesiaCmimi->cmimet()->where('monedha_id', $this->modal_id_monedha)->first()
                : null;

            $cmimiNjesi = $cmimiMonedhes ? $cmimiMonedhes->vlera : 0;
            $this->modal_vlera = round($cmimiNjesi * max($this->modal_sasia, 0), 2);
            return;
        }

        // Orë
        if ($this->modal_id_fasha) {
            $fashaOrare = OretCmimi::find($this->modal_id_fasha);
        } elseif (!$this->eshteRegjistrimParaprak) {
            $hyrja = Carbon::parse($this->mjetiZgjedhur->nisja);
            $tani  = Carbon::now();

            $totaliMinutaveReal = $hyrja->diffInMinutes($tani);
            $oreTeqendrimit = max($totaliMinutaveReal / 60, 0.01);

            $fashaOrare = $this->fashatEKategorise($this->modal_id_kategoria, $idMjeti)
                ->whereRaw('? BETWEEN `nga` AND `ne`', [$oreTeqendrimit])
                ->first();

            if (!$fashaOrare) {
                $fashaOrare = $this->fashatEKategorise($this->modal_id_kategoria, $idMjeti)
                    ->orderBy('ne', 'desc')
                    ->first();
            }

            $this->modal_id_fasha = $fashaOrare?->id;
        } else {
            $this->modal_vlera = '';
            return;
        }

        $cmimiMonedhes = $fashaOrare
            ? $fashaOrare->cmimet()->where('monedha_id', $this->modal_id_monedha)->first()
            : null;

        $this->modal_vlera = $cmimiMonedhes ? $cmimiMonedhes->vlera : '';
    }

    // Rillogarit sa herë ndryshon sasia
    public function updatedModalSasia()
    {
        $this->llogaritCmiminAutomatik();
    }

    public function updatedModalIdFasha()
    {
        $this->llogaritCmiminAutomatik();
    }

    public function updatedModalIdMonedha()
    {
        $this->llogaritCmiminAutomatik();
    }

    public function updatedModalIdKategoria()
    {
        $kategoria = KategoriaPageses::find($this->modal_id_kategoria);
        $idMjeti = $this->idMjetiE($this->mjetiZgjedhur);

        if ($kategoria && $kategoria->eshteNjesiaDite()) {
            $njesiaCmimi = $this->fashatEKategorise($this->modal_id_kategoria, $idMjeti)->first();
            $this->modal_id_fasha = $njesiaCmimi ? $njesiaCmimi->id : null;

            // Nëse është mbyllje reale, llogarit automatikisht (ceil); parapagesë mbetet 1
            $this->modal_sasia = $this->eshteRegjistrimParaprak
                ? 1
                : $this->llogaritSasineAutomatike($this->modal_id_kategoria);
        } elseif ($this->eshteRegjistrimParaprak) {
            $fashaEPare = $this->fashatEKategorise($this->modal_id_kategoria, $idMjeti)
                ->orderBy('nga')
                ->first();
            $this->modal_id_fasha = $fashaEPare ? $fashaEPare->id : null;
        } else {
            $fashaEPershtatshme = $this->gjejFashenPerKohenReale($this->modal_id_kategoria);
            $this->modal_id_fasha = $fashaEPershtatshme ? $fashaEPershtatshme->id : null;
        }

        $this->llogaritCmiminAutomatik();
    }

    // Llogarit sa "njësi" (ditë/net) janë kaluar realisht, duke rrumbullakosur NGA LART
    private function llogaritSasineAutomatike(int $idKategoria): int
    {
        if (!$this->mjetiZgjedhur) {
            return 1;
        }

        $kategoria = KategoriaPageses::find($idKategoria);
        $oreNjesie = $kategoria ? $kategoria->oreNjesiReale() : 24;

        $hyrja = Carbon::parse($this->mjetiZgjedhur->nisja);
        $tani  = Carbon::now();
        $oreReale = max($hyrja->diffInMinutes($tani) / 60, 0.01);

        return (int) ceil($oreReale / $oreNjesie);
    }

    // Kontrollon nëse koha e paguar (fasha orare ose sasia*orëNjësie) është kaluar
    public function statusiSkadimit(Operacionet $mjeti): array
    {
        $transaksioni = $mjeti->transaksioni;
        if (!$transaksioni) {
            return ['skaduar' => false, 'paguar' => false, 'oreLejuara' => null, 'oreReale' => null];
        }

        $hyrja = Carbon::parse($mjeti->nisja);
        $tani  = Carbon::now();
        $oreReale = max($hyrja->diffInMinutes($tani) / 60, 0);

        $kategoria = $transaksioni->prenotimi;
        $fasha     = $transaksioni->fashaOrare;
        $oreLejuara = null;

        $njesiTePaguara = null;
        $emriKategorise = $kategoria->kategoria ?? null;
        $eshteNjesiDite = $kategoria && $kategoria->eshteNjesiaDite();

        if ($fasha && $fasha->ora_nisje && $fasha->ora_mbarimi) {
            $skadimiDatetime = $this->llogaritSkadimineOresFikse($hyrja, $fasha, $transaksioni->sasia ?? 1);
            $oreLejuara = round($hyrja->diffInMinutes($skadimiDatetime) / 60, 2);
        } elseif ($eshteNjesiDite) {
            $oreNjesie = $kategoria->oreNjesiReale();
            $njesiTePaguara = $this->njesiPlotaTePaguara($mjeti, $kategoria->id);
            $oreLejuara = $oreNjesie * $njesiTePaguara;
        } elseif ($fasha) {
            $oreLejuara = $fasha->ne;
        }

        $skaduar = $oreLejuara !== null && $oreReale > $oreLejuara;

        return [
            'skaduar'        => $skaduar,
            'paguar'         => $transaksioni->status_pagesa === 'paguar',
            'oreLejuara'     => $oreLejuara,
            'oreReale'       => round($oreReale, 2),
            'njesiTePaguara' => $njesiTePaguara,
            'emriKategorise' => $emriKategorise,
            'eshteNjesiDite' => $eshteNjesiDite,
        ];
    }

    private function perputhetMeTabin(Operacionet $mjeti): bool
    {
        if ($this->tabiMjetetPrezent === 'all') {
            return true;
        }

        $statusi = $this->statusiSkadimit($mjeti);

        return match ($this->tabiMjetetPrezent) {
            'skaduar'   => $statusi['skaduar'],
            'parapagim' => !$statusi['skaduar'] && $statusi['paguar'],
            'pa_paguar' => !$statusi['skaduar'] && !$statusi['paguar'],
            default     => true,
        };
    }

    private function numroMjetetPrezentSipasStatusit($mjetet): array
    {
        $numrat = ['all' => $mjetet->count(), 'parapagim' => 0, 'pa_paguar' => 0, 'skaduar' => 0];

        foreach ($mjetet as $mjeti) {
            $statusi = $this->statusiSkadimit($mjeti);

            if ($statusi['skaduar']) {
                $numrat['skaduar']++;
            } elseif ($statusi['paguar']) {
                $numrat['parapagim']++;
            } else {
                $numrat['pa_paguar']++;
            }
        }

        return $numrat;
    }

    public function updatedKerkoTarge()
    {
        $this->faqjaPrezent = 1;
    }

    public function updatedTabiMjetetPrezent()
    {
        $this->faqjaPrezent = 1;
    }

    public function faqjaTjeterPrezent()
    {
        $this->faqjaPrezent++;
    }

    public function faqjaMbrapaPrezent()
    {
        if ($this->faqjaPrezent > 1) {
            $this->faqjaPrezent--;
        }
    }

    private function njesiPlotaTePaguara(Operacionet $mjeti, int $idKategoriaPlote): int
    {
        // Bazohemi VETËM te sasia reale e ruajtur në databazë (jo te çmimi/vlera)
        return (int) TransaksioniOperacionit::where('id_operacionit', $mjeti->id)
            ->where('id_prenotimit', $idKategoriaPlote)
            ->whereIn('status_pagesa', ['paguar', 'pagese_shtese'])
            ->sum('sasia');
    }

    // Kontrollon nëse gjysma (ditë ose natë) është regjistruar tashmë si e paguar
    private function gjysmaEshteTashmePaguar(Operacionet $mjeti, $kategoriaPlote): bool
    {
        $familja = $this->gjejFamiljeGjysmesh($kategoriaPlote);

        if (!$familja) {
            return false;
        }

        $idetGjysme = collect([$familja['dite']?->id, $familja['nate']?->id])
            ->filter()->values();

        if ($idetGjysme->isEmpty()) {
            return false;
        }

        // Kontrollojmë VETËM pagesat shtesë (pagese_shtese), jo pagesën origjinale bazë
        return TransaksioniOperacionit::where('id_operacionit', $mjeti->id)
            ->whereIn('id_prenotimit', $idetGjysme)
            ->where('status_pagesa', 'pagese_shtese')
            ->exists();
    }

    // Mbledh sasinë totale të paguar deri tani (rreshti origjinal + çdo shtesë e mëparshme)
    private function sasiaTotalePaguar(Operacionet $mjeti): int
    {
        return (int) TransaksioniOperacionit::where('id_operacionit', $mjeti->id)
            ->whereIn('status_pagesa', ['paguar', 'pagese_shtese'])
            ->sum('sasia');
    }

    // Gjen momentin exact kur skadon fasha me orar fiks
    // Trajton kalimin e mesnatës (p.sh. Natë 19:00-09:00) dhe sasi > 1 (disa ditë/net)
    private function llogaritSkadimineOresFikse(Carbon $hyrja, $fasha, int $sasia): Carbon
    {
        $skadimi = Carbon::parse($hyrja->format('Y-m-d') . ' ' . $fasha->ora_mbarimi);

        if ($skadimi->lessThanOrEqualTo($hyrja)) {
            $skadimi->addDay();
        }

        if ($sasia > 1) {
            $skadimi->addDays($sasia - 1);
        }

        return $skadimi;
    }

    // Llogarit sa duhet paguar shtesë krahasuar me vlerën ekzistuese të paguar
    private function llogaritVlerenShtese(Operacionet $operacioni, array $statusi): void
    {
        $transaksioni = $operacioni->transaksioni;
        if (!$transaksioni) {
            $this->vlera_shtese = 0;
            $this->vlera_dite_plota = 0;
            return;
        }

        $kategoria = $transaksioni->prenotimi;
        $fasha     = $transaksioni->fashaOrare;
        $monedhaId = $transaksioni->monedha;
        $idMjeti   = $this->idMjetiE($operacioni);

        // Orari fiks (Ditë 09-19 / Natë 19-09) — akumulon TË GJITHA periudhat e kaluara
        if ($fasha && $fasha->ora_nisje && $fasha->ora_mbarimi) {
            $hyrja = Carbon::parse($operacioni->nisja);
            $tani  = Carbon::now();

            $momentiSkadimit = $this->llogaritSkadimineOresFikse($hyrja, $fasha, $transaksioni->sasia ?? 1);

            $rezultati = $this->mblidhVlerenPerPeriudhatFikse($momentiSkadimit, $tani, $monedhaId, $idMjeti);

            $this->vlera_shtese           = $rezultati['vlera'];
            $this->shtese_id_fasha        = $rezultati['fasha']?->id;
            $this->shtese_sasia           = 1;
            $this->detajetPeriudhaveFikse = $rezultati['detaje'];

            $this->vlera_dite_plota           = 0;
            $this->shtese_sasia_plote         = 0;
            $this->shtese_id_kategoria_plote  = null;
            $this->shtese_id_kategoria_gjysme = null;
            $this->shtese_id_fasha_gjysme     = null;
            return;
        }

        // Kategori me njësi ditë
        if ($kategoria && $kategoria->eshteNjesiaDite()) {
            $oreNjesie = $kategoria->oreNjesiReale();
            $njesiPlotaNevojshme = (int) floor($statusi['oreReale'] / $oreNjesie);
            $mbetetOre = $statusi['oreReale'] - ($njesiPlotaNevojshme * $oreNjesie);

            $njesiPlotaTePaguara = $this->njesiPlotaTePaguara($operacioni, $kategoria->id);
            $njesiPlotaPerTuPaguar = max($njesiPlotaNevojshme - $njesiPlotaTePaguara, 0);

            $njesiaCmimi = $this->fashatEKategorise($kategoria->id, $idMjeti)->first();
            $cmimiNjesi  = $njesiaCmimi
                ? ($njesiaCmimi->cmimet()->where('monedha_id', $monedhaId)->first()->vlera ?? 0)
                : 0;

            // Nëse mbetja kalon gjysmën e njësisë (>12h), e trajtojmë si NJË NJËSI TJETËR E PLOTË
            $gjysmaOre = $oreNjesie / 2;
            if ($mbetetOre > $gjysmaOre) {
                $njesiPlotaPerTuPaguar += 1;
                $mbetetOre = 0;
            }

            $this->vlera_dite_plota          = round($cmimiNjesi * $njesiPlotaPerTuPaguar, 2);
            $this->shtese_sasia_plote        = $njesiPlotaPerTuPaguar;
            $this->shtese_id_kategoria_plote = $kategoria->id;
            $this->shtese_id_fasha_plote     = $njesiaCmimi?->id;

            $vleraGjysme = 0;
            $duhetGjysme = $mbetetOre > 0.01 && !$this->gjysmaEshteTashmePaguar($operacioni, $kategoria);

            if ($duhetGjysme) {
                $gjysma = $this->gjenGjysmenAktive($kategoria, $idMjeti);
                if ($gjysma) {
                    $cmimiGjysmes = $gjysma['fasha']->cmimet()->where('monedha_id', $monedhaId)->first();
                    $vleraGjysme = $cmimiGjysmes ? round($cmimiGjysmes->vlera, 2) : 0;
                    $this->shtese_id_kategoria_gjysme = $gjysma['kategoria']->id;
                    $this->shtese_id_fasha_gjysme     = $gjysma['fasha']->id;
                } else {
                    $this->shtese_id_kategoria_gjysme = null;
                    $this->shtese_id_fasha_gjysme = null;
                }
            } else {
                $this->shtese_id_kategoria_gjysme = null;
                $this->shtese_id_fasha_gjysme = null;
            }

            $this->vlera_shtese = round($this->vlera_dite_plota + $vleraGjysme, 2);
            $this->detajetPeriudhaveFikse = [];
            return;
        }

        // Orë (bracket i thjeshtë)
        if ($kategoria) {
            $fashat = $this->fashatEKategorise($kategoria->id, $idMjeti)->orderBy('nga')->get();

            $fashaEPershtatshme = $fashat
                ->first(fn($f) => $statusi['oreReale'] >= $f->nga && $statusi['oreReale'] <= $f->ne)
                ?? $this->fashatEKategorise($kategoria->id, $idMjeti)->orderBy('ne', 'desc')->first();

            $cmimiIRi = $fashaEPershtatshme
                ? ($fashaEPershtatshme->cmimet()->where('monedha_id', $monedhaId)->first()->vlera ?? 0)
                : 0;

            $this->shtese_id_fasha = $fashaEPershtatshme?->id;
            $this->shtese_sasia = 1;
            $this->vlera_shtese = max(round($cmimiIRi - $transaksioni->vlera, 2), 0);
            $this->vlera_dite_plota = 0;
            $this->shtese_sasia_plote = 0;
            $this->shtese_id_kategoria_plote = null;
            $this->shtese_id_kategoria_gjysme = null;
            $this->shtese_id_fasha_gjysme = null;
            $this->detajetPeriudhaveFikse = [];
        }
    }

    private function gjenGjysmenNeMomentin($kategoriaPlote, Carbon $momenti, ?int $idMjeti = null): ?array
    {
        $familja = $this->gjejFamiljeGjysmesh($kategoriaPlote);
        if (!$familja) {
            return null;
        }

        $ora = $momenti->format('H:i');
        $kandidatet = collect([$familja['dite'], $familja['nate']])->filter();

        foreach ($kandidatet as $gjysme) {
            $fasha = $this->fashatEKategorise($gjysme->id, $idMjeti)
                ->whereNotNull('ora_nisje')->whereNotNull('ora_mbarimi')
                ->first();

            if (!$fasha) continue;

            $brenda = $fasha->ora_nisje <= $fasha->ora_mbarimi
                ? ($ora >= $fasha->ora_nisje && $ora < $fasha->ora_mbarimi)
                : ($ora >= $fasha->ora_nisje || $ora < $fasha->ora_mbarimi);

            if ($brenda) {
                return ['kategoria' => $gjysme, 'fasha' => $fasha];
            }
        }

        return null;
    }

    private function mblidhVlerenPerPeriudhatFikse(Carbon $nga, Carbon $deri, int $monedhaId, ?int $idMjeti = null): array
    {
        $vleraTotale = 0;
        $cursor = $nga->copy();
        $fashaFundit = null;
        $siguria = 0; // mbrojtje kundër loop të pafundme
        $detaje = [];

        while ($cursor->lessThan($deri) && $siguria < 60) {
            $fasha = $this->gjejFashenFikseNeMomentin($cursor, $idMjeti);
            if (!$fasha) {
                break;
            }

            $fashaFundit = $fasha;

            $fundiBllokut = Carbon::parse($cursor->format('Y-m-d') . ' ' . $fasha->ora_mbarimi);
            if ($fundiBllokut->lessThanOrEqualTo($cursor)) {
                $fundiBllokut->addDay();
            }

            $cmimiMonedhes = $fasha->cmimet()->where('monedha_id', $monedhaId)->first();
            $vleraSegmenti = $cmimiMonedhes ? $cmimiMonedhes->vlera : 0;
            $vleraTotale += $vleraSegmenti;

            $detaje[] = [
                'kategoria' => $fasha->kategoria->kategoria ?? '-',
                'nga'       => $cursor->format('d/m H:i'),
                'deri'      => $fundiBllokut->format('d/m H:i'),
                'vlera'     => round($vleraSegmenti, 2),
            ];

            $cursor = $fundiBllokut->greaterThan($deri) ? $deri->copy() : $fundiBllokut;
            $siguria++;
        }

        return ['vlera' => round($vleraTotale, 2), 'fasha' => $fashaFundit, 'detaje' => $detaje];
    }

    private function gjejFashenFikseNeMomentin(Carbon $momenti, ?int $idMjeti = null): ?OretCmimi
    {
        $ora = $momenti->format('H:i');

        return OretCmimi::whereNotNull('ora_nisje')
            ->whereNotNull('ora_mbarimi')
            ->when($idMjeti, fn($q) => $q->where('id_kategoria_mjeti', $idMjeti))
            ->get()
            ->first(function ($f) use ($ora) {
                if ($f->ora_nisje <= $f->ora_mbarimi) {
                    return $ora >= $f->ora_nisje && $ora < $f->ora_mbarimi;
                }
                // fasha kalon mesnatën (p.sh. 19:00 - 09:00)
                return $ora >= $f->ora_nisje || $ora < $f->ora_mbarimi;
            });
    }

    // Gjen fashën tjetër me orar fiks që mbulon orën aktuale, duke përjashtuar fashën aktuale të skaduar
    private function gjejFashenFikseAktive(int $idFashesAktuale, ?int $idMjeti = null): ?OretCmimi
    {
        $tani = Carbon::now()->format('H:i');

        return OretCmimi::whereNotNull('ora_nisje')
            ->whereNotNull('ora_mbarimi')
            ->where('id', '!=', $idFashesAktuale)
            ->when($idMjeti, fn($q) => $q->where('id_kategoria_mjeti', $idMjeti))
            ->get()
            ->first(function ($f) use ($tani) {
                if ($f->ora_nisje <= $f->ora_mbarimi) {
                    return $tani >= $f->ora_nisje && $tani < $f->ora_mbarimi;
                }
                return $tani >= $f->ora_nisje || $tani < $f->ora_mbarimi;
            });
    }

    public function ruajPagesenShtese()
    {
        $this->validate([
            'vlera_shtese' => 'required|numeric|min:0',
        ]);

        if (!$this->mjetiSkaduarZgjedhur || !$this->mjetiSkaduarZgjedhur->transaksioni) {
            return;
        }

        if ($this->vlera_shtese <= 0) {
            $this->addError('vlera_shtese', 'Nuk ka asnjë vlerë për t\'u paguar.');
            return;
        }

        $operacioni = $this->mjetiSkaduarZgjedhur;
        $transaksioniOriginal = $operacioni->transaksioni;

        $rreshtiIRi = TransaksioniOperacionit::create([
            'id_operacionit'  => $operacioni->id,
            'id_prenotimit'   => $this->shtese_id_kategoria_plote ?? $transaksioniOriginal->id_prenotimit,
            'id_fashes_orare' => $this->shtese_id_fasha_plote ?? $this->shtese_id_fasha,
            'sasia'           => max($this->shtese_sasia_plote, 1),
            'status_pagesa'   => 'pagese_shtese',
            'monedha'         => $transaksioniOriginal->monedha,
            'vlera'           => $this->vlera_shtese,
        ]);

        $operatoriOrigjinal = $operacioni->id_operatori;
        $operatoriAktual    = Auth::id();

        if ($operatoriOrigjinal && $operatoriOrigjinal != $operatoriAktual) {
            \App\Models\Admin\NdryshimiOperatorit::create([
                'id_transaksionit' => $rreshtiIRi->id,
                'operatori_pare'   => $operatoriOrigjinal,
                'operatori_dyte'   => $operatoriAktual,
                'pagesa_e_re'      => $this->vlera_shtese,
            ]);
            $operacioni->id_operatori = $operatoriAktual;
        }

        $operacioni->ikja = now();
        $operacioni->status = 'larguar';
        $operacioni->save();

        // Printimi LAN i kuponit të pagesës shtesë
        $rezultatiPrintimit = app(KuponParkimiService::class)->printoPagesenShtese($operacioni, $rreshtiIRi);

        if (!$rezultatiPrintimit) {
            $rawContent = app(KuponParkimiService::class)->buildPagesenShteseRaw($operacioni, $rreshtiIRi);
            $this->dispatch('printo-ne-bluetooth', rawContent: $rawContent);
        }

        session()->flash(
            'success',
            ($rezultatiPrintimit ? 'Operacioni u mbyll me sukses! ' : 'Operacioni u mbyll (printeri LAN nuk u përgjigj, po provohet Bluetooth). ')
            . 'Pagesë shtesë: +' . number_format($this->vlera_shtese, 2) . ' ' . ($transaksioniOriginal->monedhaRelacion->kodi ?? '')
        );

        $this->mbyllModalSkadimi();
    }

    // Përdoruesi zgjedh të shtojë vlerën shtesë mbi çmimin origjinal
    public function shtoVlerenShtese()
    {
        if (!$this->mjetiSkaduarZgjedhur) {
            return;
        }

        $this->eshteRegjistrimParaprak = false;
        $this->inicializoModalinPerOperacionin($this->mjetiSkaduarZgjedhur);

        $vleraEPaguarEkzistuese = $this->mjetiSkaduarZgjedhur->transaksioni->vlera ?? 0;
        $this->modal_vlera = round($vleraEPaguarEkzistuese + $this->vlera_shtese, 2);

        $this->mbyllModalSkadimi();
    }

    // Përdoruesi zgjedh të injorojë tejkalimin e vogël dhe vazhdon normalisht
    public function injoroVlerenShtese()
    {
        if (!$this->mjetiSkaduarZgjedhur) {
            return;
        }

        $this->eshteRegjistrimParaprak = false;
        $this->inicializoModalinPerOperacionin($this->mjetiSkaduarZgjedhur);

        $this->mbyllModalSkadimi();
    }

    // Mbyll modalin e skadimit pa vazhduar (Anulo)
    public function mbyllModalSkadimi()
    {
        $this->shfaqModalSkadimi = false;
        $this->mjetiSkaduarZgjedhur = null;
        $this->detajetSkadimit = [];
        $this->vlera_shtese = 0;
        $this->vlera_dite_plota = 0;
        $this->shtese_sasia_plote = 0;
        $this->shtese_id_kategoria_plote = null;
        $this->shtese_id_fasha_plote = null;
        $this->shtese_id_kategoria_gjysme = null;
        $this->shtese_id_fasha_gjysme = null;
        $this->shtese_id_fasha = null;
        $this->shtese_sasia = null;
        $this->detajetPeriudhaveFikse = [];
        $this->shfaqDetajetShtese = false;
    }

    // ════════════════════════════════════════════════
    // Ruajtja e transaksionit — vepron ndryshe në bazë të flag-ut
    // ════════════════════════════════════════════════
    public function ruajTransaksionin()
    {
        $this->validate([
            'modal_id_kategoria' => 'required',
            'modal_id_monedha'   => 'required',
            'modal_vlera'        => 'required|numeric|min:0',
        ], [
            'modal_vlera.required' => 'Ju lutem vendosni vlerën e pagesës.',
        ]);

        if (!$this->mjetiZgjedhur) {
            return;
        }

        // NEW: pa fashë/çmim të konfiguruar nuk lejohet ruajtja (kolona id_fashes_orare është NOT NULL)
        if (!$this->modal_id_fasha) {
            $this->addError('modal_id_fasha', 'Nuk ka çmim/fashë të konfiguruar për këtë shërbim dhe këtë kategori mjeti. Shtoje te "Konfigurimi i Orëve".');
            return;
        }

        $tedhenatTransaksionit = [
            'id_operacionit'  => $this->mjetiZgjedhur->id,
            'id_prenotimit'   => $this->modal_id_kategoria,
            'id_fashes_orare' => $this->modal_id_fasha,
            'sasia'           => $this->modal_sasia,
            'status_pagesa'   => 'paguar',
            'monedha'         => $this->modal_id_monedha,
            'vlera'           => $this->modal_vlera,
        ];

        $vleraEVjeter = $this->transaksioniIRuajtur->vlera ?? null;

        // NEW: delete + create në një transaksion, që mos humbasë pagesa e vjetër nëse dështon
        $transaksioniIRi = \Illuminate\Support\Facades\DB::transaction(function () use ($tedhenatTransaksionit) {
            TransaksioniOperacionit::where('id_operacionit', $this->mjetiZgjedhur->id)->delete();

            return TransaksioniOperacionit::create($tedhenatTransaksionit);
        });

        if ($this->eshteRegjistrimParaprak) {
            // Printimi i kuponit të parapagesës — LAN, me fallback Bluetooth
            $rezultatiPrintimit = app(KuponParkimiService::class)->printoParapagesen(
                $this->mjetiZgjedhur, $transaksioniIRi, $this->metoda_pageses
            );

            if (!$rezultatiPrintimit) {
                $rawContent = app(KuponParkimiService::class)->buildParapagesenRaw(
                    $this->mjetiZgjedhur, $transaksioniIRi, $this->metoda_pageses
                );
                $this->dispatch('printo-ne-bluetooth', rawContent: $rawContent);
            }

            session()->flash(
                $rezultatiPrintimit ? 'success' : 'error',
                $rezultatiPrintimit
                    ? 'Pagesa u regjistrua paraprakisht. Mjeti mbetet Prezent në parking.'
                    : 'Printeri LAN nuk u përgjigj — po provohet printimi Bluetooth...'
            );
        } else {
            $operatoriOrigjinal = $this->mjetiZgjedhur->id_operatori;
            $operatoriAktual    = Auth::id();

            if ($operatoriOrigjinal && $operatoriOrigjinal != $operatoriAktual) {
                $pagesaERe = ($vleraEVjeter !== null && $this->modal_vlera > $vleraEVjeter)
                    ? $this->modal_vlera
                    : null;

                \App\Models\Admin\NdryshimiOperatorit::create([
                    'id_transaksionit' => $transaksioniIRi->id,
                    'operatori_pare'   => $operatoriOrigjinal,
                    'operatori_dyte'   => $operatoriAktual,
                    'pagesa_e_re'      => $pagesaERe,
                ]);

                $this->mjetiZgjedhur->id_operatori = $operatoriAktual;
            }

            $this->mjetiZgjedhur->ikja = now();
            $this->mjetiZgjedhur->status = 'larguar';
            $this->mjetiZgjedhur->save();

            // Printimi i kuponit të daljes — LAN, me fallback Bluetooth
            $rezultatiPrintimit = app(KuponParkimiService::class)->printoDaljen(
                $this->mjetiZgjedhur, $transaksioniIRi, $this->metoda_pageses
            );

            if (!$rezultatiPrintimit) {
                $rawContent = app(KuponParkimiService::class)->buildDaljaRaw(
                    $this->mjetiZgjedhur, $transaksioniIRi, $this->metoda_pageses
                );
                $this->dispatch('printo-ne-bluetooth', rawContent: $rawContent);
            }

            session()->flash(
                $rezultatiPrintimit ? 'success' : 'error',
                $rezultatiPrintimit
                    ? 'Operacioni u mbyll me sukses!'
                    : 'Printeri LAN nuk u përgjigj — po provohet printimi Bluetooth...'
            );
        }

        $this->klickedTarga = false;
        $this->mjetiZgjedhur = null;
        $this->koha_qendrimit = '';
        $this->modal_vlera = '';
        $this->modal_id_fasha = null;
        $this->transaksioniIRuajtur = null;
        $this->eshteRegjistrimParaprak = false;
        $this->modal_sasia = 1;
    }

    // Regjistrimi normal (pa pagesë)
    public function ruajOperacionin()
    {
        $targaPastruar = strtoupper(str_replace(' ', '', $this->targa));

        $this->validate([
            'targa' => 'required|string|max:20',
            'id_kategoria_mjeti' => 'required|exists:kategorite_e_mjeteve,id',
        ], [
            'targa.required' => 'Ju lutem vendosni targën e makinës.',
            'id_kategoria_mjeti.required' => 'Zgjidhni kategorinë e mjetit.',
        ]);

        $ekziston = Operacionet::where('status', 'prezent')
            ->whereRaw("REPLACE(UPPER(targa), ' ', '') = ?", [$targaPastruar])
            ->exists();

        if ($ekziston) {
            $this->addError('targa', 'Kjo targë është tashmë e regjistruar si "Prezent" në parking.');
            return;
        }

        $operacioni = Operacionet::create([
            'targa'              => $targaPastruar,
            'id_kategoria_mjeti' => $this->id_kategoria_mjeti,
            'id_operatori'       => Auth::user()->id,
            'nisja'              => now(),
            'ikja'               => null,
            'status'             => 'prezent',
        ]);

        // Printimi LAN i kuponit të hyrjes
        $rezultatiPrintimit = app(KuponParkimiService::class)->printoHyrjen($operacioni);

        if (!$rezultatiPrintimit) {
            $rawContent = app(KuponParkimiService::class)->buildHyrjaRaw($operacioni);
            $this->dispatch('printo-ne-bluetooth', rawContent: $rawContent);
        }

        session()->flash(
            $rezultatiPrintimit ? 'success' : 'error',
            $rezultatiPrintimit
                ? 'Mjeti u regjistrua me sukses si Prezent!'
                : 'Printeri LAN nuk u përgjigj — po provohet printimi Bluetooth...'
        );

        $this->reset('targa', 'shuma_paguar', 'eshte_paguar');

        $kategoriaDefault = KategoriaPageses::where('is_default', 1)->first();
        if ($kategoriaDefault) {
            $this->id_kategoria = $kategoriaDefault->id;
        }

        $this->id_kategoria_mjeti = $this->idKategoriaMjetiDefault();
    }

    public function shfaqDetajetMjetitLarguar($id)
    {
        $this->mjetiLarguarZgjedhur = Operacionet::with([
            'operatori',
            'kategoriaMjetit',
            'transaksioni.prenotimi',
            'transaksioni.fashaOrare',
            'transaksioni.monedha',
            'transaksioni.operatori',
            'transaksionet.prenotimi',
            'transaksionet.monedhaRelacioni',
        ])->find($id);

        if ($this->mjetiLarguarZgjedhur) {
            $this->shfaqModalDetajet = true;
        }
    }

    public function printoHistorikunMjetitLarguar()
    {
        if (!$this->mjetiLarguarZgjedhur) {
            return;
        }

        // Printimi LAN i kuponit të historikut
        $rezultati = app(KuponParkimiService::class)->printoHistorikunMjetit($this->mjetiLarguarZgjedhur);

        if ($rezultati) {
            session()->flash('success_modal', 'Kuponi i historikut u dërgua në printer!');
        } else {
            $rawContent = app(KuponParkimiService::class)->buildHistorikuRaw($this->mjetiLarguarZgjedhur);
            $this->dispatch('printo-ne-bluetooth', rawContent: $rawContent);
            session()->flash('success_modal', 'Printeri LAN nuk u përgjigj — po provohet printimi Bluetooth...');
        }
    }

    // Gjen çiftin {dite, nate} pavarësisht nëse $kategoria është vetë 24h,
    // ose është vetë njëra gjysmë (p.sh. paguar direkt "Natë")
    private function gjejFamiljeGjysmesh($kategoria): ?array
    {
        // Rasti 1: kategoria e paguar ËSHTË vetë kategoria 24h (ka të dyja fushat)
        if ($kategoria->id_kategoria_gjysme_dite && $kategoria->id_kategoria_gjysme_nate) {
            return [
                'dite' => $kategoria->gjysmaDite,
                'nate' => $kategoria->gjysmaNate,
            ];
        }

        // Rasti 2: kategoria e paguar ËSHTË vetë një gjysmë — kërkojmë prindin 24h
        $prindi = KategoriaPageses::where('id_kategoria_gjysme_dite', $kategoria->id)
            ->orWhere('id_kategoria_gjysme_nate', $kategoria->id)
            ->first();

        if ($prindi) {
            return [
                'dite' => $prindi->gjysmaDite,
                'nate' => $prindi->gjysmaNate,
            ];
        }

        return null;
    }

    private function gjenGjysmenAktive($kategoriaPlote, ?int $idMjeti = null): ?array
    {
        $familja = $this->gjejFamiljeGjysmesh($kategoriaPlote);

        if (!$familja) {
            return null;
        }

        $tani = Carbon::now()->format('H:i');
        $kandidatet = collect([$familja['dite'], $familja['nate']])->filter();

        foreach ($kandidatet as $gjysme) {
            $fasha = $this->fashatEKategorise($gjysme->id, $idMjeti)
                ->whereNotNull('ora_nisje')->whereNotNull('ora_mbarimi')
                ->first();

            if (!$fasha) continue;

            $brenda = $fasha->ora_nisje <= $fasha->ora_mbarimi
                ? ($tani >= $fasha->ora_nisje && $tani < $fasha->ora_mbarimi)
                : ($tani >= $fasha->ora_nisje || $tani < $fasha->ora_mbarimi);

            if ($brenda) {
                return ['kategoria' => $gjysme, 'fasha' => $fasha];
            }
        }

        return null;
    }

    public function perditesoMjetinPrezent($id)
    {
        $operacioni = Operacionet::with('transaksioni')->find($id);
        if (!$operacioni) {
            return;
        }

        $this->mjetiId = $operacioni->id;
        $this->edit_nisja = Carbon::parse($operacioni->nisja)->format('Y-m-d\TH:i');
        $this->edit_id_kategoria_mjeti = $this->idMjetiE($operacioni);

        $transaksioni = $operacioni->transaksioni;

        if ($transaksioni) {
            // Mbush formën me pagesën ekzistuese (nëse ka parapagesë)
            $this->edit_id_kategoria = $transaksioni->id_prenotimit;
            $this->edit_id_monedha   = $transaksioni->monedha;
            $this->edit_id_fasha     = $transaksioni->id_fashes_orare;
            $this->edit_sasia        = $transaksioni->sasia ?? 1;
            $this->edit_vlera        = $transaksioni->vlera;
        } else {
            // S'ka pagesë ende — vendos default-et
            $kategoriaDefault = KategoriaPageses::where('is_default', 1)->first();
            $this->edit_id_kategoria = $kategoriaDefault?->id;

            $monedhaDefault = Monedhat::where('kodi', 'ALL')->first();
            $this->edit_id_monedha = $monedhaDefault?->id;

            $this->edit_id_fasha = null;
            $this->edit_sasia = 1;
            $this->edit_vlera = '';
        }

        $this->isEditModalOpen = true;
    }

    public function updatedEditIdKategoria()
    {
        $kategoria = KategoriaPageses::find($this->edit_id_kategoria);

        if ($kategoria && $kategoria->eshteNjesiaDite()) {
            $njesiaCmimi = $this->fashatEKategorise($this->edit_id_kategoria, $this->edit_id_kategoria_mjeti)->first();
            $this->edit_id_fasha = $njesiaCmimi?->id;
        } else {
            $fashaEPare = $this->fashatEKategorise($this->edit_id_kategoria, $this->edit_id_kategoria_mjeti)
                ->orderBy('nga')->first();
            $this->edit_id_fasha = $fashaEPare?->id;
        }

        // S'e prekim edit_vlera — mbetet ajo që është paguar realisht
    }

    // Kur ndryshon kategoria e mjetit, rizgjedhim fashën nga çmimet e kategorisë së re
    public function updatedEditIdKategoriaMjeti()
    {
        $this->updatedEditIdKategoria();
    }

    public function updatedEditIdFasha()
    {
        // S'e rillogarisim automatikisht — operatori e vendos vlerën manualisht
    }

    public function updatedEditIdMonedha()
    {
        // S'e rillogarisim automatikisht
    }

    public function updatedEditSasia()
    {
        // S'e rillogarisim automatikisht
    }

    // Llogaritje çmimi automatike për formën e editimit
    private function llogaritCmiminEditit()
    {
        $kategoria = KategoriaPageses::find($this->edit_id_kategoria);
        if (!$kategoria) {
            return;
        }

        if ($kategoria->eshteNjesiaDite()) {
            $njesiaCmimi = $this->edit_id_fasha
                ? OretCmimi::find($this->edit_id_fasha)
                : $this->fashatEKategorise($this->edit_id_kategoria, $this->edit_id_kategoria_mjeti)->first();

            $cmimiMonedhes = $njesiaCmimi
                ? $njesiaCmimi->cmimet()->where('monedha_id', $this->edit_id_monedha)->first()
                : null;

            $cmimiNjesi = $cmimiMonedhes ? $cmimiMonedhes->vlera : 0;
            $this->edit_vlera = round($cmimiNjesi * max($this->edit_sasia, 0), 2);
        } else {
            $fashaOrare = $this->edit_id_fasha ? OretCmimi::find($this->edit_id_fasha) : null;
            $cmimiMonedhes = $fashaOrare
                ? $fashaOrare->cmimet()->where('monedha_id', $this->edit_id_monedha)->first()
                : null;
            $this->edit_vlera = $cmimiMonedhes ? $cmimiMonedhes->vlera : '';
        }
    }

    public function rikalkuloVlerenEditit()
    {
        $this->llogaritCmiminEditit();
    }

    public function ruajEditimin()
    {
        $this->validate([
            'edit_nisja'              => 'required|date',
            'edit_id_kategoria'       => 'required',
            'edit_id_kategoria_mjeti' => 'required|exists:kategorite_e_mjeteve,id',
            'edit_id_monedha'         => 'required',
            'edit_vlera'              => 'nullable|numeric|min:0',
        ], [
            'edit_nisja.required' => 'Ju lutem vendosni orën e hyrjes.',
            'edit_id_kategoria_mjeti.required' => 'Zgjidhni kategorinë e mjetit.',
        ]);

        // Nëse s'ka fashë të zgjedhur, provo ta gjejmë automatikisht para se të ruajmë
        if (!$this->edit_id_fasha) {
            $kategoria = KategoriaPageses::find($this->edit_id_kategoria);

            if ($kategoria && $kategoria->eshteNjesiaDite()) {
                $njesiaCmimi = $this->fashatEKategorise($this->edit_id_kategoria, $this->edit_id_kategoria_mjeti)->first();
                $this->edit_id_fasha = $njesiaCmimi?->id;
            } else {
                $fashaEPare = $this->fashatEKategorise($this->edit_id_kategoria, $this->edit_id_kategoria_mjeti)
                    ->orderBy('nga')->first();
                $this->edit_id_fasha = $fashaEPare?->id;
            }
        }

        // Nëse ende s'gjendet dot asnjë fashë, ndalo ruajtjen me mesazh të qartë
        if (!$this->edit_id_fasha) {
            $this->addError('edit_id_kategoria', 'Kjo kategori s\'ka asnjë çmim/fashë të konfiguruar për këtë kategori mjeti. Kontrollo te "Konfigurimi i Orëve" përpara se ta ruash.');
            return;
        }

        $operacioni = Operacionet::with('transaksioni')->find($this->mjetiId);
        if (!$operacioni) {
            return;
        }

        $operacioni->nisja = Carbon::parse($this->edit_nisja);
        $operacioni->id_kategoria_mjeti = $this->edit_id_kategoria_mjeti;
        $operacioni->save();

        $vlera = (float) ($this->edit_vlera ?: 0);

        if ($vlera > 0) {
            TransaksioniOperacionit::updateOrCreate(
                ['id_operacionit' => $operacioni->id, 'status_pagesa' => 'paguar'],
                [
                    'id_prenotimit'   => $this->edit_id_kategoria,
                    'id_fashes_orare' => $this->edit_id_fasha,
                    'sasia'           => $this->edit_sasia ?: 1,
                    'monedha'         => $this->edit_id_monedha,
                    'vlera'           => $vlera,
                ]
            );
        } elseif ($operacioni->transaksioni) {
            $operacioni->transaksioni->delete();
        }

        session()->flash('success', 'Të dhënat e mjetit u përditësuan me sukses!');
        $this->mbyllModalEditimi();
    }

    public function mbyllModalEditimi()
    {
        $this->isEditModalOpen = false;
        $this->mjetiId = null;
        $this->edit_nisja = null;
        $this->edit_id_kategoria = null;
        $this->edit_id_kategoria_mjeti = null;
        $this->edit_id_monedha = null;
        $this->edit_id_fasha = null;
        $this->edit_sasia = 1;
        $this->edit_vlera = '';
    }

    // Hap modalin e konfirmimit, pa fshirë ende asgjë
    public function konfirmoFshirjen($id)
    {
        $this->mjetiPerFshirje = Operacionet::find($id);

        if ($this->mjetiPerFshirje) {
            $this->shfaqModalFshirje = true;
        }
    }

    // Fshirja reale, thirret vetëm pasi operatori konfirmon në modal
    public function fshijMjetinPrezent()
    {
        if (!$this->mjetiPerFshirje) {
            return;
        }

        $operacioni = $this->mjetiPerFshirje;

        $idTransaksionesh = TransaksioniOperacionit::where('id_operacionit', $operacioni->id)->pluck('id');

        if ($idTransaksionesh->isNotEmpty()) {
            \App\Models\Admin\NdryshimiOperatorit::whereIn('id_transaksionit', $idTransaksionesh)->delete();
        }

        TransaksioniOperacionit::where('id_operacionit', $operacioni->id)->delete();
        $operacioni->delete();

        session()->flash('success', 'Mjeti u fshi me sukses së bashku me pagesat e lidhura.');

        $this->mbyllModalFshirjen();
    }

    public function mbyllModalFshirjen()
    {
        $this->shfaqModalFshirje = false;
        $this->mjetiPerFshirje = null;
    }

    // Numri rendor i radhës për sot (rifillon nga 1 çdo ditë, sipas datës së hyrjes)


    public function render()
    {
        $perFaqePrezent = 18;

        // 1. Mjetet që janë aktualisht në parking — më i fundit i pari
        $mjetePrezentQuery = Operacionet::where('status', 'prezent')
            ->with(['kategoriaMjetit', 'transaksioni.prenotimi', 'transaksioni.fashaOrare', 'transaksioni.monedha'])
            ->orderByDesc('nisja');

        if ($this->kerkoTarge) {
            // Kur kërkohet, s'ka faqe/limit dhe s'aplikohet filtri i tab-it
            $mjetePrezent = (clone $mjetePrezentQuery)
                ->where('targa', 'like', '%' . strtoupper($this->kerkoTarge) . '%')
                ->get();

            $numratTabeve = $this->numroMjetetPrezentSipasStatusit(
                (clone $mjetePrezentQuery)->get()
            );

            $faqetPrezent = 1;
        } else {
            $tePagjitha = $mjetePrezentQuery->get();

            $numratTabeve = $this->numroMjetetPrezentSipasStatusit($tePagjitha);

            $teFiltruara = $tePagjitha->filter(fn($mjeti) => $this->perputhetMeTabin($mjeti))->values();

            $faqetPrezent = max((int) ceil($teFiltruara->count() / $perFaqePrezent), 1);
            $this->faqjaPrezent = min(max($this->faqjaPrezent, 1), $faqetPrezent);

            $mjetePrezent = $teFiltruara->forPage($this->faqjaPrezent, $perFaqePrezent)->values();
        }

        // 2. Mjetet e larguara (të shërbyera)
        $queryLarguar = Operacionet::where('status', 'larguar')
            ->with('transaksioni.fashaOrare')
            ->when($this->kerkoTarge, function ($query) {
                $query->where('targa', 'like', '%' . strtoupper($this->kerkoTarge) . '%');
            });

        if ($this->tabiAktiv === 'sot') {
            $queryLarguar->whereDate('ikja', Carbon::today());
        } elseif ($this->tabiAktiv === 'dje') {
            $queryLarguar->whereDate('ikja', Carbon::yesterday());
        } elseif ($this->tabiAktiv === 'cakto_daten' && $this->dataSpecifike) {
            $queryLarguar->whereDate('ikja', $this->dataSpecifike);
        } else {
            $queryLarguar->whereDate('ikja', Carbon::today());
        }

        $mjeteLarguar = $queryLarguar->orderBy('ikja', 'desc')->get();

        // 3. Fashat orare — filtrohen sipas kategorisë së mjetit
        $fashatOrare = $this->modal_id_kategoria
            ? $this->fashatEKategorise($this->modal_id_kategoria, $this->idMjetiE($this->mjetiZgjedhur))
                ->orderBy('nga')
                ->get()
            : collect();

        $fashatOrareEdit = $this->edit_id_kategoria
            ? $this->fashatEKategorise($this->edit_id_kategoria, $this->edit_id_kategoria_mjeti)
                ->orderBy('nga')->get()
            : collect();

        $kategoriaEditAktuale = $this->edit_id_kategoria ? KategoriaPageses::find($this->edit_id_kategoria) : null;

        $kategoriaAktuale = $this->modal_id_kategoria ? KategoriaPageses::find($this->modal_id_kategoria) : null;
        $njesiaFashave = ($kategoriaAktuale && $kategoriaAktuale->eshteNjesiaDite()) ? __('ditë') : __('orë');

        return view('livewire.operatori.live-kryej-operacionet', [
            'kategorite'           => KategoriaPageses::all(),
            'kategoriteMjeteve'    => KategoriteEMjeteve::orderBy('kategoria')->get(),
            'monedhat'             => Monedhat::all(),
            'mjetePrezent'         => $mjetePrezent,
            'numratTabeve'         => $numratTabeve,
            'faqetPrezent'         => $faqetPrezent,
            'mjeteLarguar'         => $mjeteLarguar,
            'fashatOrare'          => $fashatOrare,
            'njesiaFashave'        => $njesiaFashave,
            'kategoriaAktuale'     => $kategoriaAktuale,
            'fashatOrareEdit'      => $fashatOrareEdit,
            'kategoriaEditAktuale' => $kategoriaEditAktuale,
        ])->layout('layouts.dashboard.app');
    }
}
