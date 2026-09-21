<?php

namespace App\Livewire\Lavazho;

use App\Models\Admin\Monedhat;
use App\Models\Admin\KategoriteEMjeteve;
use App\Models\Admin\LavazhoLarjetCmimi;
use App\Models\Admin\LavazhoLarjetLista;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class LiveKonfiguroCmimet extends Component
{
    use AuthorizesRequests;

    // ── Forma e shërbimit + çmimeve ──
    public $sherbimi = '';
    public $id_kategoria_mjetit = '';
    public array $cmimet_monedhave = []; // [id_monedha => vlera]

    public bool $showModal = false;
    public bool $isViewOnly = false;
    public $editingId = null;

    // ── Filtrat ──
    public string $search = '';
    public $filterKatId = '';

    // ── Modali i kategorive të mjeteve ──
    public bool $showKategoriModal = false;
    public string $kat_emri = '';
    public $editingKatId = null;

    public function mount()
    {
        // $this->authorize('lavazho.konfiguro-cmimet');
    }

    protected function rules()
    {
        $rules = [
            'sherbimi' => ['required', 'string', 'max:255'],
            'id_kategoria_mjetit' => ['required', 'exists:kategorite_e_mjeteve,id'],
        ];

        // Kërkohet çmim për çdo monedhë, edhe nëse inputi është lënë bosh
        foreach (Monedhat::pluck('id') as $monedhaId) {
            $rules['cmimet_monedhave.' . $monedhaId] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    protected function messages()
    {
        return [
            'sherbimi.required' => __('Emri i shërbimit është i detyrueshëm.'),
            'sherbimi.max' => __('Emri i shërbimit nuk mund të kalojë 255 karaktere.'),
            'id_kategoria_mjetit.required' => __('Zgjidhni kategorinë e mjetit.'),
            'id_kategoria_mjetit.exists' => __('Kategoria e zgjedhur nuk ekziston.'),
            'cmimet_monedhave.*.required' => __('Vendosni çmimin për këtë monedhë.'),
            'cmimet_monedhave.*.numeric' => __('Çmimi duhet të jetë numër i vlefshëm.'),
            'cmimet_monedhave.*.min' => __('Çmimi nuk mund të jetë negativ.'),
        ];
    }

    // ═══════════════════════════════════════
    //  SHËRBIMET & ÇMIMET
    // ═══════════════════════════════════════

    public function hapModalin()
    {
        $this->resetForma();

        if ($this->filterKatId) {
            $this->id_kategoria_mjetit = $this->filterKatId;
        }

        $this->showModal = true;
    }

    public function shikoSherbimin($sherbimiId, $kategoriaId)
    {
        $this->ngarkoKonfigurimin($sherbimiId, $kategoriaId);
        $this->isViewOnly = true;
        $this->showModal = true;
    }

    public function editSherbimin($sherbimiId, $kategoriaId)
    {
        $this->ngarkoKonfigurimin($sherbimiId, $kategoriaId);
        $this->editingId = $sherbimiId;
        $this->showModal = true;
    }

    protected function ngarkoKonfigurimin($sherbimiId, $kategoriaId)
    {
        $this->resetForma();

        $lista = LavazhoLarjetLista::find($sherbimiId);
        if (! $lista) {
            return;
        }

        $this->sherbimi = $lista->sherbimi;
        $this->id_kategoria_mjetit = $kategoriaId;

        $cmimet = LavazhoLarjetCmimi::where('id_sherbimit', $sherbimiId)
            ->where('id_kategoria_mjetit', $kategoriaId)
            ->get();

        foreach ($cmimet as $cmimi) {
            $this->cmimet_monedhave[$cmimi->id_monedhes] = $cmimi->vlera;
        }
    }

    public function ruajSherbimin()
    {
        $this->validate();

        DB::transaction(function () {
            if ($this->editingId) {
                $lista = LavazhoLarjetLista::findOrFail($this->editingId);
                $lista->update(['sherbimi' => trim($this->sherbimi)]);
            } else {
                // Nëse ekziston shërbimi me të njëjtin emër, ripërdoret (p.sh. për kategori tjetër mjeti)
                $lista = LavazhoLarjetLista::firstOrCreate(['sherbimi' => trim($this->sherbimi)]);
            }

            foreach ($this->cmimet_monedhave as $monedhaId => $vlera) {
                LavazhoLarjetCmimi::updateOrCreate(
                    [
                        'id_sherbimit' => $lista->id,
                        'id_kategoria_mjetit' => $this->id_kategoria_mjetit,
                        'id_monedhes' => $monedhaId,
                    ],
                    [
                        'vlera' => floatval($vlera),
                    ]
                );
            }
        });

        $this->showModal = false;
        $this->resetForma();
        session()->flash('message', 'Konfigurimi u ruajt me sukses.');
    }

    public function fshiSherbimin($sherbimiId, $kategoriaId)
    {
        DB::transaction(function () use ($sherbimiId, $kategoriaId) {
            LavazhoLarjetCmimi::where('id_sherbimit', $sherbimiId)
                ->where('id_kategoria_mjetit', $kategoriaId)
                ->delete();

            // Nëse shërbimi nuk ka më asnjë çmim për asnjë kategori, fshihet edhe vetë shërbimi
            if (! LavazhoLarjetCmimi::where('id_sherbimit', $sherbimiId)->exists()) {
                LavazhoLarjetLista::where('id', $sherbimiId)->delete();
            }
        });

        session()->flash('message', 'Konfigurimi u fshi me sukses.');
    }

    protected function resetForma()
    {
        $this->reset(['sherbimi', 'id_kategoria_mjetit', 'cmimet_monedhave', 'editingId', 'isViewOnly']);
        $this->resetErrorBag();
    }

    // ═══════════════════════════════════════
    //  KATEGORITË E MJETEVE
    // ═══════════════════════════════════════

    public function hapKategorite()
    {
        $this->resetFormenKategorise();
        $this->showKategoriModal = true;
    }

    public function editKategorine($id)
    {
        $kategoria = KategoriteEMjeteve::find($id);
        if (! $kategoria) {
            return;
        }

        $this->resetErrorBag();
        $this->editingKatId = $kategoria->id;
        $this->kat_emri = $kategoria->kategoria;
    }

    public function ruajKategorine()
    {
        $this->validate(
            ['kat_emri' => ['required', 'string', 'max:255']],
            [
                'kat_emri.required' => __('Emri i kategorisë është i detyrueshëm.'),
                'kat_emri.max' => __('Emri i kategorisë nuk mund të kalojë 255 karaktere.'),
            ]
        );

        $emri = trim($this->kat_emri);

        if ($this->editingKatId) {
            KategoriteEMjeteve::where('id', $this->editingKatId)->update(['kategoria' => $emri]);
        } else {
            KategoriteEMjeteve::create(['kategoria' => $emri]);
        }

        $this->resetFormenKategorise();
    }

    public function fshiKategorine($id)
    {
        $ePerdorur = LavazhoLarjetCmimi::where('id_kategoria_mjetit', $id)->exists();

        if (! $ePerdorur && Schema::hasTable('lavazho_operacionet')) {
            $ePerdorur = DB::table('lavazho_operacionet')->where('mjeti_kategoria_id', $id)->exists();
        }

        if ($ePerdorur) {
            $this->addError('kat_fshirja', __('Kjo kategori përdoret te çmimet ose operacionet dhe nuk mund të fshihet.'));
            return;
        }

        KategoriteEMjeteve::where('id', $id)->delete();

        if ($this->filterKatId == $id) {
            $this->filterKatId = '';
        }

        $this->resetFormenKategorise();
    }

    public function anuloEditimin()
    {
        $this->resetFormenKategorise();
    }

    protected function resetFormenKategorise()
    {
        $this->reset(['kat_emri', 'editingKatId']);
        $this->resetErrorBag(['kat_emri', 'kat_fshirja']);
    }

    // ═══════════════════════════════════════
    //  RENDER
    // ═══════════════════════════════════════

    public function render()
    {
        $monedhat = Monedhat::all();
        $kategorite = KategoriteEMjeteve::orderBy('kategoria')->get();

        // Një rresht në tabelë = një kombinim (shërbim + kategori mjeti)
        $rreshtat = LavazhoLarjetCmimi::with(['sherbimi', 'kategoria', 'monedha'])
            ->when($this->filterKatId, function ($query) {
                $query->where('id_kategoria_mjetit', $this->filterKatId);
            })
            ->when($this->search, function ($query) {
                $query->whereHas('sherbimi', function ($q) {
                    $q->where('sherbimi', 'like', '%' . $this->search . '%');
                });
            })
            ->get()
            ->groupBy(fn ($c) => $c->id_sherbimit . '-' . $c->id_kategoria_mjetit)
            ->map(function ($grupi) {
                $first = $grupi->first();

                return [
                    'sherbimi_id' => $first->id_sherbimit,
                    'sherbimi' => $first->sherbimi->sherbimi ?? '—',
                    'kategoria_id' => $first->id_kategoria_mjetit,
                    'kategoria' => $first->kategoria->kategoria ?? '—',
                    'cmimet' => $grupi
                        ->mapWithKeys(fn ($c) => [$c->monedha->kodi => $c->vlera])
                        ->all(),
                ];
            })
            ->sortByDesc('sherbimi_id')
            ->values();

        return view('livewire.lavazho.live-konfiguro-cmimet', [
            'monedhat' => $monedhat,
            'kategorite' => $kategorite,
            'rreshtat' => $rreshtat,
        ])->layout('layouts.dashboard.app');
    }
}
