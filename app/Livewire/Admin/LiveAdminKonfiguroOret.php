<?php

namespace App\Livewire\Admin;

use App\Models\Admin\KategoriaPageses;
use App\Models\Admin\Monedhat;
use App\Models\Admin\OretCmimi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use App\Models\Admin\KategoriteEMjeteve;

class LiveAdminKonfiguroOret extends Component
{
    use AuthorizesRequests;

    // Vetitë (Properties) e modelit dhe formave
    public $ora_fillestare;
    public $ora_limit;
    public $id_kategoria_rezervimit;
    public bool $is_orar_muri = false; // Kontrollon gjendjen e Switch-it në modal

    public array $cmimet_monedhave = [];
    public bool $showOretModal = false;
    public bool $isViewOnly = false;
    public $editingId = null;
    public ?int $filterKatId = null;
    public string $search = '';

    public $id_kategoria_mjeti;
    public ?int $filterMjetId = null;

    public function mount()
    {
        $this->authorize('admin.konfiguro-oret');
    }

    /**
     * Rregullat e Validimit Dinamik
     * Nëse $is_orar_muri është True, detyron formatin e pastër HH:MM (00:00 deri 23:59)
     * Nëse është False, lejon vetëm numra dhjetorë ose të plotë (p.sh. 12 ose 2.5)
     */
    /**
     * Rregullat e Validimit Dinamik (Tani në formatin Array për siguri maksimale)
     */
    /**
     * Rregullat e Validimit të Përditësuara për Inputin e Orës
     */
    protected function rules()
    {
        return [
            'id_kategoria_rezervimit' => ['required', 'exists:adm_kategoria_pageses,id'],
            'id_kategoria_mjeti' => ['required', 'exists:kategorite_e_mjeteve,id'],

            'ora_fillestare' => $this->is_orar_muri
                ? ['required', 'regex:/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/']
                : ['required', 'numeric', 'min:0'],

            'ora_limit' => $this->is_orar_muri
                ? ['required', 'regex:/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/']
                : ['required', 'numeric', 'min:0'],

            'cmimet_monedhave.*' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function messages()
    {
        return [
            'id_kategoria_mjeti.required' => __('Zgjidhni kategorinë e mjetit.'),
            'ora_fillestare.regex' => __('Formati duhet të jetë ekzaktësisht HH:MM (p.sh. 14:00 ose 09:00).'),
            'ora_limit.regex' => __('Formati duhet të jetë ekzaktësisht HH:MM (p.sh. 22:00 ose 19:00).'),
            'ora_fillestare.numeric' => __('Vendosni një numër të vlefshëm për orët.'),
            'ora_limit.numeric' => __('Vendosni një numër të vlefshëm për orët.'),
        ];
    }

    public function hapModalin()
    {
        $this->reset(['ora_fillestare', 'ora_limit', 'cmimet_monedhave', 'editingId', 'isViewOnly', 'id_kategoria_rezervimit', 'id_kategoria_mjeti', 'is_orar_muri']);
        $this->resetErrorBag();

        if ($this->filterKatId) {
            $this->id_kategoria_rezervimit = $this->filterKatId;
        }

        // Kategoria e mjetit: fillimisht filtri aktiv, përndryshe ajo me is_default
        $this->id_kategoria_mjeti = $this->filterMjetId
            ?: KategoriteEMjeteve::where('is_default', true)->value('id');

        $this->showOretModal = true;
    }

    public function shikoOret($id)
    {
        $this->reset(['ora_fillestare', 'ora_limit', 'cmimet_monedhave', 'id_kategoria_rezervimit', 'id_kategoria_mjeti', 'is_orar_muri']);
        $fasha = DB::table('adm_oret_cmimi')->where('id', $id)->first();
        if (! $fasha) return;

        if ($fasha->ora_nisje) {
            $this->is_orar_muri = true;
            $this->ora_fillestare = $fasha->ora_nisje;
            $this->ora_limit = $fasha->ora_mbarimi;
        } else {
            $this->is_orar_muri = false;
            $this->ora_fillestare = $fasha->nga;
            $this->ora_limit = $fasha->ne;
        }

        $this->id_kategoria_rezervimit = $fasha->id_kategoria_rezervimit;
        $this->id_kategoria_mjeti = $fasha->id_kategoria_mjeti;

        $cmimet = DB::table('adm_cmimi_sipas_monedhes')->where('interval_id', $id)->get();
        foreach ($cmimet as $cmimi) {
            $this->cmimet_monedhave[$cmimi->monedha_id] = $cmimi->vlera;
        }
        $this->isViewOnly = true;
        $this->showOretModal = true;
    }

    public function editOret($id)
    {
        $this->reset(['ora_fillestare', 'ora_limit', 'cmimet_monedhave', 'isViewOnly', 'id_kategoria_rezervimit', 'id_kategoria_mjeti', 'is_orar_muri']);
        $this->resetErrorBag();
        $this->editingId = $id;

        $fasha = DB::table('adm_oret_cmimi')->where('id', $id)->first();
        if (! $fasha) return;

        if ($fasha->ora_nisje) {
            $this->is_orar_muri = true;
            $this->ora_fillestare = $fasha->ora_nisje;
            $this->ora_limit = $fasha->ora_mbarimi;
        } else {
            $this->is_orar_muri = false;
            $this->ora_fillestare = $fasha->nga;
            $this->ora_limit = $fasha->ne;
        }

        $this->id_kategoria_rezervimit = $fasha->id_kategoria_rezervimit;
        $this->id_kategoria_mjeti = $fasha->id_kategoria_mjeti;

        $cmimet = DB::table('adm_cmimi_sipas_monedhes')->where('interval_id', $id)->get();
        foreach ($cmimet as $cmimi) {
            $this->cmimet_monedhave[$cmimi->monedha_id] = $cmimi->vlera;
        }
        $this->showOretModal = true;
    }

    public function ruajOret()
    {
        $this->validate();

        $katId = $this->id_kategoria_rezervimit;
        $mjetId = $this->id_kategoria_mjeti;

        $nga = $this->is_orar_muri ? 0 : floatval($this->ora_fillestare);
        $ne = $this->is_orar_muri ? 0 : floatval($this->ora_limit);
        $ora_nisje = $this->is_orar_muri ? $this->ora_fillestare : null;
        $ora_mbarimi = $this->is_orar_muri ? $this->ora_limit : null;

        DB::transaction(function () use ($nga, $ne, $ora_nisje, $ora_mbarimi, $katId, $mjetId) {
            $data = [
                'id_kategoria_rezervimit' => $katId,
                'id_kategoria_mjeti' => $mjetId,
                'nga' => $nga,
                'ne' => $ne,
                'ora_nisje' => $ora_nisje,
                'ora_mbarimi' => $ora_mbarimi,
                'updated_at' => now(),
            ];

            if ($this->editingId) {
                DB::table('adm_oret_cmimi')->where('id', $this->editingId)->update($data);
                $intervalId = $this->editingId;
                DB::table('adm_cmimi_sipas_monedhes')->where('interval_id', $intervalId)->delete();
            } else {
                $data['created_at'] = now();
                $intervalId = DB::table('adm_oret_cmimi')->insertGetId($data);
            }

            foreach ($this->cmimet_monedhave as $monedhaId => $vlera) {
                DB::table('adm_cmimi_sipas_monedhes')->insert([
                    'interval_id' => $intervalId,
                    'monedha_id' => $monedhaId,
                    'vlera' => floatval($vlera),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->showOretModal = false;
        $this->reset(['ora_fillestare', 'ora_limit', 'cmimet_monedhave', 'editingId', 'isViewOnly', 'id_kategoria_rezervimit', 'id_kategoria_mjeti', 'is_orar_muri']);
        session()->flash('message', 'Konfigurimi u ruajt me sukses.');
    }

    public function fshiOret($id)
    {
        DB::table('adm_oret_cmimi')->where('id', $id)->delete();
        session()->flash('message', 'Intervali u fshi me sukses.');
    }

    public function render()
    {
        $monedhat = Monedhat::all();

        $fashat = OretCmimi::with(['cmimet.monedha', 'kategoria', 'kategoriaMjetit'])
            ->when($this->filterKatId, function ($query) {
                $query->where('id_kategoria_rezervimit', $this->filterKatId);
            })
            ->when($this->filterMjetId, function ($query) {
                $query->where('id_kategoria_mjeti', $this->filterMjetId);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('ora_nisje', 'like', '%' . $this->search . '%')
                        ->orWhere('ora_mbarimi', 'like', '%' . $this->search . '%')
                        ->orWhere('nga', 'like', '%' . $this->search . '%')
                        ->orWhere('ne', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        $konfigurimet = $fashat->map(function ($fasha) {
            $fasha->cmimet_mapped = $fasha->cmimet->pluck('vlera', 'monedha.kodi')->toArray();
            return $fasha;
        });

        return view('livewire.admin.live-admin-konfiguro-oret', [
            'monedhat' => $monedhat,
            'konfigurimet' => $konfigurimet,
            'kategorite' => KategoriaPageses::get(),
            'kategoriteMjeteve' => KategoriteEMjeteve::orderBy('kategoria')->get(),
        ])->layout('layouts.dashboard.app');
    }
}
