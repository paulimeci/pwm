<?php

namespace App\Livewire\Lavazho;

use App\Models\Admin\KategoriteEMjeteve;
use App\Models\Admin\LavazhoKryejOperacionet;
use App\Models\Admin\LavazhoLarjetCmimi;
use App\Models\Admin\LavazhoLarjetLista;
use App\Models\Admin\Monedhat;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

use App\Services\Admin\KuponLavazhoService;

class LiveLavazhoKryejOperacionet extends Component
{
    use AuthorizesRequests;

    // ── Forma e regjistrimit ──
    public $targa = '';
    public $reg_kategoria = '';
    public $reg_sherbimi = '';
    public bool $eshte_paguar = false;
    // ── Modali i pagesës gjatë regjistrimit ──
    public bool $showModalRegPagesa = false;
    public $reg_modal_vlera = '';
    public $monedhaDefaultId = null;

    // ── Kërkimi, tab-et, faqosja (mjetet prezente) ──
    public string $kerkoTarge = '';
    public string $tabiMjetetPrezent = 'all'; // all, paguar, pa_paguar
    public int $faqjaPrezent = 1;

    // ── Mjetet e larguara ──
    public string $tabiAktiv = 'sot'; // sot, dje, cakto_daten
    public $dataSpecifike;

    // ── Modali i pagesës / largimit ──
    // I thjeshtuar: shfaq vetëm targën dhe lejon modifikimin e vlerës.
    public bool $showModalPagesa = false;
    public $mjetiId = null;
    public $modal_vlera = '';
    public bool $modal_eshte_paguar = false;

    // ── Modali i editimit ──
    public bool $showModalEdit = false;
    public $editId = null;
    public $edit_targa = '';
    public $edit_nisja = '';
    public $edit_kategoria = '';
    public $edit_sherbimi = '';
    public $edit_monedha = '';
    public $edit_vlera = '';
    public bool $edit_paguar = false;

    // ── Modali i detajeve (të larguarit) dhe i fshirjes ──
    public $detajeId = null;
    public $fshirjeId = null;

    public function mount()
    {
        $this->monedhaDefaultId = Monedhat::where('kodi', 'ALL')->value('id');
    }

    // ═══════════════════════════════════════
    //  NDIHMËSE
    // ═══════════════════════════════════════

    private function pastroTargen(string $targa): string
    {
        return strtoupper(str_replace(' ', '', $targa));
    }

    private function targaEkziston(string $targa, ?int $perjashtoId = null): bool
    {
        return LavazhoKryejOperacionet::where('status', 'prezent')
            ->whereRaw("REPLACE(UPPER(targa), ' ', '') = ?", [$targa])
            ->when($perjashtoId, fn ($q) => $q->where('id', '!=', $perjashtoId))
            ->exists();
    }

    private function gjejCmimin($sherbimiId, $kategoriaId, $monedhaId): ?float
    {
        if (! $sherbimiId || ! $kategoriaId || ! $monedhaId) {
            return null;
        }

        $vlera = LavazhoLarjetCmimi::where('id_sherbimit', $sherbimiId)
            ->where('id_kategoria_mjetit', $kategoriaId)
            ->where('id_monedhes', $monedhaId)
            ->value('vlera');

        return $vlera !== null ? (float) $vlera : null;
    }

    // Vetëm shërbimet që kanë çmim të konfiguruar për kategorinë e zgjedhur
    private function sherbimetPerKategori($kategoriaId)
    {
        if (! $kategoriaId) {
            return collect();
        }

        $ids = LavazhoLarjetCmimi::where('id_kategoria_mjetit', $kategoriaId)
            ->pluck('id_sherbimit')
            ->unique();

        return LavazhoLarjetLista::whereIn('id', $ids)->orderBy('sherbimi')->get();
    }

    // Nëse shërbimi aktual nuk vlen për kategorinë e re, zgjedh të parin e disponueshëm
    private function korrigjoSherbimin($sherbimiAktual, $kategoriaId)
    {
        $sherbimet = $this->sherbimetPerKategori($kategoriaId);

        if ($sherbimet->contains('id', (int) $sherbimiAktual)) {
            return $sherbimiAktual;
        }

        return $sherbimet->first()?->id;
    }

    // ═══════════════════════════════════════
    //  REGJISTRIMI
    // ═══════════════════════════════════════


    public function updatedEshtePaguar($value)
    {
        if (! $value) {
            return;
        }

        $this->resetErrorBag();

        // Modali hapet vetëm nëse fushat bazë janë plotësuar
        $mungon = false;

        if (trim((string) $this->targa) === '') {
            $this->addError('targa', 'Ju lutem vendosni targën e makinës.');
            $mungon = true;
        }
        if (! $this->reg_kategoria) {
            $this->addError('reg_kategoria', 'Zgjidhni kategorinë e mjetit.');
            $mungon = true;
        }
        if (! $this->reg_sherbimi) {
            $this->addError('reg_sherbimi', 'Zgjidhni shërbimin.');
            $mungon = true;
        }

        if ($mungon) {
            $this->eshte_paguar = false;
            return;
        }

        $cmimi = $this->gjejCmimin($this->reg_sherbimi, $this->reg_kategoria, $this->monedhaDefaultId);

        $this->reg_modal_vlera = $cmimi ?? '';
        $this->showModalRegPagesa = true;
    }


    public function konfirmoModalRegPagesa()
    {
        $this->validate([
            'reg_modal_vlera' => 'required|numeric|min:0',
        ], [
            'reg_modal_vlera.required' => 'Ju lutem vendosni vlerën e pagesës.',
            'reg_modal_vlera.numeric'  => 'Vlera duhet të jetë numër.',
        ]);

        $vlera = (float) $this->reg_modal_vlera;

        $this->showModalRegPagesa = false;

        // Regjistrohet direkt me vlerën e modalit (e modifikuar ose jo)
        if (! $this->regjistroOperacionin($vlera)) {
            $this->eshte_paguar = false;
        }
    }

    private function regjistroOperacionin(?float $vleraManuale = null): bool
    {
        $this->validate([
            'targa'         => 'required|string|max:20',
            'reg_kategoria' => 'required|exists:kategorite_e_mjeteve,id',
            'reg_sherbimi'  => 'required|exists:lavazho_larjet_lista,id',
        ], [
            'targa.required'         => 'Ju lutem vendosni targën e makinës.',
            'reg_kategoria.required' => 'Zgjidhni kategorinë e mjetit.',
            'reg_sherbimi.required'  => 'Zgjidhni shërbimin.',
        ]);

        $targaPastruar = $this->pastroTargen($this->targa);

        if ($this->targaEkziston($targaPastruar)) {
            $this->addError('targa', 'Kjo targë është tashmë e regjistruar si "Prezent" në lavazho.');
            return false;
        }

        $vlera = $vleraManuale ?? $this->gjejCmimin($this->reg_sherbimi, $this->reg_kategoria, $this->monedhaDefaultId);

        if ($vlera === null) {
            $this->addError('reg_sherbimi', 'Ky shërbim nuk ka çmim të konfiguruar për këtë kategori (ALL).');
            return false;
        }

        $operacioni = LavazhoKryejOperacionet::create([
            'id_operatori'       => Auth::id(),
            'targa'              => $targaPastruar,
            'id_operacionit'     => $this->reg_sherbimi,
            'mjeti_kategoria_id' => $this->reg_kategoria,
            'id_monedha'         => $this->monedhaDefaultId,
            'vlera'              => $vlera,
            'status'             => 'prezent',
            'pagesa'             => $this->eshte_paguar ? 'po' : 'jo',
            'nisja'              => now(),
        ]);

        if ($this->eshte_paguar) {
            $rezultatiPrintimit = app(KuponLavazhoService::class)->printoFaturen($operacioni, false);

            if (!$rezultatiPrintimit) {
                $rawContent = app(KuponLavazhoService::class)->buildFaturaRaw($operacioni, false);
                $this->dispatch('printo-ne-bluetooth', rawContent: $rawContent);
            }
        } else {
            $rezultatiPrintimit = app(KuponLavazhoService::class)->printoHyrjen($operacioni);

            if (!$rezultatiPrintimit) {
                $rawContent = app(KuponLavazhoService::class)->buildHyrjaRaw($operacioni);
                $this->dispatch('printo-ne-bluetooth', rawContent: $rawContent);
            }
        }

        $this->reset('targa', 'reg_kategoria', 'reg_sherbimi', 'eshte_paguar', 'reg_modal_vlera', 'showModalRegPagesa');
        $this->resetErrorBag();

        session()->flash(
            'success',
            $rezultatiPrintimit
                ? 'Mjeti u regjistrua me sukses si Prezent!'
                : 'Mjeti u regjistrua! Printeri LAN nuk u përgjigj — po provohet printimi Bluetooth...'
        );

        return true;
    }


    public function mbyllModalRegPagesa()
    {
        // Anulimi: kthe switch-in në "jo"
        $this->showModalRegPagesa = false;
        $this->eshte_paguar = false;
        $this->reg_modal_vlera = '';
        $this->resetErrorBag('reg_modal_vlera');
    }

    public function updatedRegKategoria()
    {
        $this->reg_sherbimi = $this->korrigjoSherbimin($this->reg_sherbimi, $this->reg_kategoria);
    }

    public function ruajOperacionin()
    {
        // Nëse "Paguar?" është aktiv, vlera merret nga modali
        if ($this->eshte_paguar) {
            $this->updatedEshtePaguar(true);
            return;
        }

        $this->regjistroOperacionin();
    }


    // ═══════════════════════════════════════
    //  MODALI I PAGESËS / LARGIMIT
    //  (i thjeshtuar: vetëm targa + vlera e modifikueshme)
    // ═══════════════════════════════════════

    public function hapModalPagesen($id)
    {
        $op = LavazhoKryejOperacionet::find($id);

        if (! $op || $op->status !== 'prezent') {
            return;
        }

        $this->resetErrorBag();
        $this->mjetiId = $op->id;
        $this->modal_vlera = $op->vlera;
        $this->modal_eshte_paguar = $op->pagesa === 'po';

        $this->showModalPagesa = true;
    }

    public function perfundoOperacionin()
    {
        $this->validate([
            'modal_vlera' => 'required|numeric|min:0',
        ], [
            'modal_vlera.required' => 'Ju lutem vendosni vlerën e pagesës.',
            'modal_vlera.numeric'  => 'Vlera duhet të jetë numër.',
        ]);

        $op = LavazhoKryejOperacionet::find($this->mjetiId);

        if (! $op || $op->status !== 'prezent') {
            $this->mbyllModalPagesen();
            return;
        }

        $op->update([
            'vlera'  => (float) $this->modal_vlera,
            'pagesa' => 'po',
            'status' => 'larguar',
            'ikja'   => now(),
        ]);

        // Printim i kuponit të daljes/faturës
        $rezultatiPrintimit = app(KuponLavazhoService::class)->printoFaturen($op, true);

        if (!$rezultatiPrintimit) {
            $rawContent = app(KuponLavazhoService::class)->buildFaturaRaw($op, true);
            $this->dispatch('printo-ne-bluetooth', rawContent: $rawContent);
        }

        session()->flash(
            'success',
            $rezultatiPrintimit
                ? 'Operacioni u mbyll me sukses!'
                : 'Operacioni u mbyll! Printeri LAN nuk u përgjigj — po provohet printimi Bluetooth...'
        );
        $this->mbyllModalPagesen();
    }

    public function mbyllModalPagesen()
    {
        $this->reset(['showModalPagesa', 'mjetiId', 'modal_vlera', 'modal_eshte_paguar']);
        $this->resetErrorBag();
    }

    // ═══════════════════════════════════════
    //  EDITIMI
    // ═══════════════════════════════════════

    public function perditesoMjetin($id)
    {
        $op = LavazhoKryejOperacionet::find($id);

        if (! $op) {
            return;
        }

        $this->resetErrorBag();
        $this->editId = $op->id;
        $this->edit_targa = $op->targa;
        $this->edit_nisja = Carbon::parse($op->nisja)->format('Y-m-d\TH:i');
        $this->edit_kategoria = $op->mjeti_kategoria_id;
        $this->edit_sherbimi = $op->id_operacionit;
        $this->edit_monedha = $op->id_monedha;
        $this->edit_vlera = $op->vlera;
        $this->edit_paguar = $op->pagesa === 'po';
        $this->showModalEdit = true;
    }

    public function updatedEditKategoria()
    {
        $this->edit_sherbimi = $this->korrigjoSherbimin($this->edit_sherbimi, $this->edit_kategoria);
        $this->rillogaritEdit();
    }

    public function updatedEditSherbimi()
    {
        $this->rillogaritEdit();
    }

    public function updatedEditMonedha()
    {
        $this->rillogaritEdit();
    }

    private function rillogaritEdit()
    {
        $cmimi = $this->gjejCmimin($this->edit_sherbimi, $this->edit_kategoria, $this->edit_monedha);
        $this->edit_vlera = $cmimi !== null ? $cmimi : '';
    }

    public function ruajEditimin()
    {
        $this->validate([
            'edit_targa'     => 'required|string|max:20',
            'edit_nisja'     => 'required|date',
            'edit_kategoria' => 'required|exists:kategorite_e_mjeteve,id',
            'edit_sherbimi'  => 'required|exists:lavazho_larjet_lista,id',
            'edit_monedha'   => 'required',
            'edit_vlera'     => 'required|numeric|min:0',
        ], [
            'edit_targa.required' => 'Ju lutem vendosni targën.',
            'edit_nisja.required' => 'Ju lutem vendosni orën e hyrjes.',
            'edit_vlera.required' => 'Ju lutem vendosni vlerën.',
        ]);

        $op = LavazhoKryejOperacionet::find($this->editId);

        if (! $op) {
            return;
        }

        $targaPastruar = $this->pastroTargen($this->edit_targa);

        if ($op->status === 'prezent' && $this->targaEkziston($targaPastruar, $op->id)) {
            $this->addError('edit_targa', 'Kjo targë është tashmë e regjistruar si "Prezent".');
            return;
        }

        $op->update([
            'targa'              => $targaPastruar,
            'nisja'              => Carbon::parse($this->edit_nisja),
            'mjeti_kategoria_id' => $this->edit_kategoria,
            'id_operacionit'     => $this->edit_sherbimi,
            'id_monedha'         => $this->edit_monedha,
            'vlera'              => (float) $this->edit_vlera,
            'pagesa'             => $this->edit_paguar ? 'po' : 'jo',
        ]);

        session()->flash('success', 'Të dhënat e mjetit u përditësuan me sukses!');
        $this->mbyllModalEditimi();
    }

    public function mbyllModalEditimi()
    {
        $this->reset([
            'showModalEdit', 'editId', 'edit_targa', 'edit_nisja', 'edit_kategoria',
            'edit_sherbimi', 'edit_monedha', 'edit_vlera', 'edit_paguar',
        ]);
        $this->resetErrorBag();
    }

    // ═══════════════════════════════════════
    //  FSHIRJA (vetëm admin)
    // ═══════════════════════════════════════

    public function konfirmoFshirjen($id)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        if (LavazhoKryejOperacionet::whereKey($id)->exists()) {
            $this->fshirjeId = $id;
        }
    }

    public function fshijMjetin()
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        if ($this->fshirjeId) {
            LavazhoKryejOperacionet::where('id', $this->fshirjeId)->delete();
            session()->flash('success', 'Mjeti u fshi me sukses.');
        }

        $this->mbyllModalFshirjen();
    }

    public function mbyllModalFshirjen()
    {
        $this->fshirjeId = null;
    }

    // ═══════════════════════════════════════
    //  DETAJET E MJETIT TË LARGUAR
    // ═══════════════════════════════════════

    public function shfaqDetajet($id)
    {
        $this->detajeId = $id;
    }

    public function mbyllDetajet()
    {
        $this->detajeId = null;
    }

    // ═══════════════════════════════════════
    //  FAQOSJA
    // ═══════════════════════════════════════

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

    // ═══════════════════════════════════════
    //  RENDER
    // ═══════════════════════════════════════

    public function render()
    {
        $perFaqe = 18;
        $with = ['sherbimi', 'kategoria', 'monedha'];

        // ── Mjetet prezente ──
        $baza = LavazhoKryejOperacionet::where('status', 'prezent');

        $numrat = [
            'all'       => (clone $baza)->count(),
            'paguar'    => (clone $baza)->where('pagesa', 'po')->count(),
            'pa_paguar' => (clone $baza)->where('pagesa', 'jo')->count(),
        ];

        $queryPrezent = (clone $baza)->with($with)->orderByDesc('nisja');

        if ($this->kerkoTarge !== '') {
            // Gjatë kërkimit: pa tab, pa faqosje
            $mjetePrezent = $queryPrezent
                ->where('targa', 'like', '%' . strtoupper($this->kerkoTarge) . '%')
                ->get();
            $faqetPrezent = 1;
        } else {
            if ($this->tabiMjetetPrezent === 'paguar') {
                $queryPrezent->where('pagesa', 'po');
            } elseif ($this->tabiMjetetPrezent === 'pa_paguar') {
                $queryPrezent->where('pagesa', 'jo');
            }

            $totali = (clone $queryPrezent)->count();
            $faqetPrezent = max((int) ceil($totali / $perFaqe), 1);
            $this->faqjaPrezent = min(max($this->faqjaPrezent, 1), $faqetPrezent);

            $mjetePrezent = $queryPrezent
                ->skip(($this->faqjaPrezent - 1) * $perFaqe)
                ->take($perFaqe)
                ->get();
        }

        // ── Mjetet e larguara ──
        $queryLarguar = LavazhoKryejOperacionet::where('status', 'larguar')
            ->with($with)
            ->when($this->kerkoTarge !== '', function ($q) {
                $q->where('targa', 'like', '%' . strtoupper($this->kerkoTarge) . '%');
            });

        if ($this->tabiAktiv === 'dje') {
            $queryLarguar->whereDate('ikja', Carbon::yesterday());
        } elseif ($this->tabiAktiv === 'cakto_daten' && $this->dataSpecifike) {
            $queryLarguar->whereDate('ikja', $this->dataSpecifike);
        } else {
            $queryLarguar->whereDate('ikja', Carbon::today());
        }

        $mjeteLarguar = $queryLarguar->orderByDesc('ikja')->get();

        $totaletLarguar = $mjeteLarguar
            ->groupBy('id_monedha')
            ->map(fn ($g) => [
                'kodi'  => $g->first()->monedha->kodi ?? '',
                'total' => $g->sum('vlera'),
            ])
            ->values();

        // ── Parapamja e çmimit te forma e regjistrimit ──
        $cmimiPreview = $this->gjejCmimin($this->reg_sherbimi, $this->reg_kategoria, $this->monedhaDefaultId);

        return view('livewire.lavazho.live-lavazho-kryej-operacionet', [
            'kategorite'     => KategoriteEMjeteve::orderBy('kategoria')->get(),
            'monedhat'       => Monedhat::all(),
            'sherbimetReg'   => $this->sherbimetPerKategori($this->reg_kategoria),
            'sherbimetEdit'  => $this->sherbimetPerKategori($this->edit_kategoria),
            'cmimiPreview'   => $cmimiPreview,
            'mjetePrezent'   => $mjetePrezent,
            'numrat'         => $numrat,
            'faqetPrezent'   => $faqetPrezent,
            'mjeteLarguar'   => $mjeteLarguar,
            'totaletLarguar' => $totaletLarguar,
            // Me relacionet e ngarkuara që modali i thjeshtuar të ketë targën/shërbimin/monedhën pa query shtesë
            'mjetiModal'     => $this->mjetiId
                ? LavazhoKryejOperacionet::with(['sherbimi', 'monedha'])->find($this->mjetiId)
                : null,
            'mjetiDetaje'    => $this->detajeId
                ? LavazhoKryejOperacionet::with(['operatori', 'sherbimi', 'kategoria', 'monedha'])->find($this->detajeId)
                : null,
            'mjetiFshirje'   => $this->fshirjeId ? LavazhoKryejOperacionet::find($this->fshirjeId) : null,
        ])->layout('layouts.dashboard.app');
    }
}
