<?php

namespace App\Livewire\Lavazho;

use App\Models\Admin\Monedhat;
use App\Models\Admin\LavazhoKryejOperacionet;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

use App\Exports\Lavazho\DetajetDitesLavazhoExport;
use App\Exports\Lavazho\RaportiPergjithshemLavazho;

use Maatwebsite\Excel\Facades\Excel;

class LiveLavazhoRaportet extends Component
{
    // Lloji i periudhës: 'java' | 'muaji' | 'percaktuar'
    public $tipiPeriudhes = 'java';

    // Kur zgjidhet "Java"
    public $javaZgjedhur = 'aktuale'; // aktuale | e_shkuar | 2javet | 3javet | 4javet

    // Kur zgjidhet "Muaji"
    public $muajiZgjedhur;
    public $vitiZgjedhur;

    // Kur zgjidhet "Përcakto"
    public $data_nga;
    public $data_deri;

    // Filtri sipas operatorit (opsional)
    public $operatoriZgjedhur = '';

    // Modali i detajeve të ditës
    public $detajetMjeteve = [];
    public $dataEPerzgjedhur = '';
    public $shfaqModalDetaje = false;

    // Modali i editimit/fshirjes së një operacioni
    public $shfaqModalEdit = false;
    public $operacioniId;
    public $editTarga;
    public $editSherbimi;
    public $editKategoria;
    public $editMonedha;
    public $editVlera;
    public $editPaguar;

    public function mount()
    {
        $this->muajiZgjedhur = now()->month;
        $this->vitiZgjedhur  = now()->year;
        $this->data_nga  = now()->startOfMonth()->format('Y-m-d');
        $this->data_deri = now()->format('Y-m-d');
    }

    public function zgjidhTipin($tip)
    {
        $this->tipiPeriudhes = $tip;
    }

    public function zgjidhOperatorin($id)
    {
        $this->operatoriZgjedhur = $id;
    }

    /**
     * Qendra e vetme që përcakton fillimin/fundin e periudhës,
     * sipas $tipiPeriudhes së zgjedhur. (Njësoj si te Parkimi.)
     */
    private function resolveDateRange(): array
    {
        $sot = Carbon::now();

        switch ($this->tipiPeriudhes) {

            case 'muaji':
                $fillimi = Carbon::createFromDate($this->vitiZgjedhur, $this->muajiZgjedhur, 1)->startOfMonth();
                $fundi   = $fillimi->copy()->endOfMonth();
                break;

            case 'percaktuar':
                $fillimi = $this->data_nga
                    ? Carbon::parse($this->data_nga)->startOfDay()
                    : $sot->copy()->startOfMonth();
                $fundi = $this->data_deri
                    ? Carbon::parse($this->data_deri)->endOfDay()
                    : $sot->copy()->endOfDay();
                break;

            case 'java':
            default:
                [$fillimi, $fundi] = match ($this->javaZgjedhur) {
                    'e_shkuar' => [$sot->copy()->subWeek()->startOfWeek(), $sot->copy()->subWeek()->endOfWeek()],
                    '2javet'   => [$sot->copy()->subWeeks(2)->startOfWeek(), $sot->copy()->endOfWeek()],
                    '3javet'   => [$sot->copy()->subWeeks(3)->startOfWeek(), $sot->copy()->endOfWeek()],
                    '4javet'   => [$sot->copy()->subWeeks(4)->startOfWeek(), $sot->copy()->endOfWeek()],
                    default    => [$sot->copy()->startOfWeek(), $sot->copy()->endOfWeek()], // 'aktuale'
                };
                break;
        }

        return [$fillimi, $fundi];
    }

    /**
     * Ndërton raportin ditor: nr. mjetesh + totalet sipas monedhës.
     * Numërohen vetëm operacionet e MBYLLURA (status = larguar) dhe të PAGUARA (pagesa = po).
     */
    private function ndertoRaportinFormatizuar($fillimi, $fundi)
    {
        $idMonedhaLek = Monedhat::where('kodi', 'ALL')->value('id');

        $operacionet = LavazhoKryejOperacionet::query()
            ->join('adm_monedhat', 'lavazho_kryej_operacionet.id_monedha', '=', 'adm_monedhat.id')
            ->selectRaw('DATE(lavazho_kryej_operacionet.ikja) as data_ikjes')
            ->selectRaw('lavazho_kryej_operacionet.id_monedha as monedha_id')
            ->selectRaw('adm_monedhat.kodi as monedha_kodi')
            ->selectRaw('COUNT(*) as nr_mjeteve')
            ->selectRaw('SUM(lavazho_kryej_operacionet.vlera) as totali_vlera')
            ->where('lavazho_kryej_operacionet.status', 'larguar')
            ->where('lavazho_kryej_operacionet.pagesa', 'po')
            ->whereNotNull('lavazho_kryej_operacionet.ikja')
            ->whereBetween('lavazho_kryej_operacionet.ikja', [$fillimi, $fundi])
            ->when($this->operatoriZgjedhur, function ($query) {
                $query->where('lavazho_kryej_operacionet.id_operatori', $this->operatoriZgjedhur);
            })
            ->groupBy('data_ikjes', 'monedha_id', 'monedha_kodi')
            ->orderByDesc('data_ikjes')
            ->get();

        $raportetFormatizuar = [];
        foreach ($operacionet as $o) {
            $data = $o->data_ikjes;

            if (!isset($raportetFormatizuar[$data])) {
                $raportetFormatizuar[$data] = [
                    'data'             => $data,
                    'nr_mjeteve'       => 0,
                    'pagesa_lek'       => 0,
                    'monedhat_e_tjera' => [],
                ];
            }

            if ($o->monedha_id == $idMonedhaLek) {
                $raportetFormatizuar[$data]['pagesa_lek'] += $o->totali_vlera;
            } else {
                if (!isset($raportetFormatizuar[$data]['monedhat_e_tjera'][$o->monedha_kodi])) {
                    $raportetFormatizuar[$data]['monedhat_e_tjera'][$o->monedha_kodi] = 0;
                }
                $raportetFormatizuar[$data]['monedhat_e_tjera'][$o->monedha_kodi] += $o->totali_vlera;
            }

            $raportetFormatizuar[$data]['nr_mjeteve'] += $o->nr_mjeteve;
        }

        return collect($raportetFormatizuar)->values();
    }

    public function shfaqDetajetEDates($data)
    {
        $this->dataEPerzgjedhur = Carbon::parse($data)->format('d/m/Y');

        $this->detajetMjeteve = LavazhoKryejOperacionet::query()
            ->with(['sherbimi', 'kategoria', 'monedha', 'operatori'])
            ->whereDate('ikja', $data)
            ->where('status', 'larguar')
            ->when($this->operatoriZgjedhur, function ($query) {
                $query->where('id_operatori', $this->operatoriZgjedhur);
            })
            ->orderBy('ikja')
            ->get()
            ->toArray();

        $this->shfaqModalDetaje = true;
    }

    public function mbyllModalin()
    {
        $this->shfaqModalDetaje = false;
        $this->detajetMjeteve = [];
    }

    public function editoOperacionin($id)
    {
        $op = LavazhoKryejOperacionet::find($id);

        if ($op) {
            $this->operacioniId  = $op->id;
            $this->editTarga     = $op->targa;
            $this->editSherbimi  = $op->id_operacionit;
            $this->editKategoria = $op->mjeti_kategoria_id;
            $this->editMonedha   = $op->id_monedha;
            $this->editVlera     = $op->vlera;
            $this->editPaguar    = $op->pagesa === 'po';

            $this->shfaqModalEdit = true;
        }
    }

    public function ruajNdryshimet()
    {
        $op = LavazhoKryejOperacionet::find($this->operacioniId);

        if ($op) {
            $op->update([
                'targa'              => strtoupper(str_replace(' ', '', $this->editTarga)),
                'id_operacionit'     => $this->editSherbimi,
                'mjeti_kategoria_id' => $this->editKategoria,
                'id_monedha'         => $this->editMonedha,
                'vlera'              => $this->editVlera,
                'pagesa'             => $this->editPaguar ? 'po' : 'jo',
            ]);

            $this->shfaqModalEdit = false;

            $dataFormatValue = Carbon::parse(str_replace('/', '-', $this->dataEPerzgjedhur))->format('Y-m-d');
            $this->shfaqDetajetEDates($dataFormatValue);
        }
    }

    public function fshiOperacionin()
    {
        $op = LavazhoKryejOperacionet::find($this->operacioniId);

        if ($op) {
            $op->delete();

            $this->shfaqModalEdit = false;

            $dataFormatValue = Carbon::parse(str_replace('/', '-', $this->dataEPerzgjedhur))->format('Y-m-d');
            $this->shfaqDetajetEDates($dataFormatValue);
        }
    }

    public function eksportoDetajetNeExcel()
    {
        if (empty($this->detajetMjeteve)) {
            return;
        }

        $emriSkedarit = 'lavazho_detajet_' . str_replace('/', '-', $this->dataEPerzgjedhur) . '.xlsx';

        return Excel::download(
            new DetajetDitesLavazhoExport($this->detajetMjeteve, $this->dataEPerzgjedhur),
            $emriSkedarit
        );
    }

    public function eksportoRaportinNeExcel()
    {
        [$fillimi, $fundi] = $this->resolveDateRange();

        $raportet = $this->ndertoRaportinFormatizuar($fillimi, $fundi);

        $etiketaPeriudhes = $fillimi->format('d-m-Y') . '_deri_' . $fundi->format('d-m-Y');

        if ($this->operatoriZgjedhur) {
            $emriOperatorit = User::find($this->operatoriZgjedhur)?->name;
            $etiketaPeriudhes .= $emriOperatorit ? '_' . str_replace(' ', '-', $emriOperatorit) : '';
        }

        $emriSkedarit = 'lavazho_bilanci_' . $etiketaPeriudhes . '.xlsx';

        return Excel::download(
            new RaportiPergjithshemLavazho($raportet, $etiketaPeriudhes),
            $emriSkedarit
        );
    }

    public function render()
    {
        [$fillimi, $fundi] = $this->resolveDateRange();

        $raportetFormatizuar = $this->ndertoRaportinFormatizuar($fillimi, $fundi);

        // Lista e operatorëve që kanë të paktën 1 operacion lavazho, për dropdown-in e filtrit.
        // KËRKON relacionin hasMany 'operacionetLavazho' në modelin User -> ndryshoje emrin
        // nëse e ke të quajtur ndryshe.
        $operatoret = User::query()
            ->whereHas('operacionetLavazho')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.lavazho.live-lavazho-raportet', [
            'raportet'   => $raportetFormatizuar,
            'fillimi'    => $fillimi,
            'fundi'      => $fundi,
            'operatoret' => $operatoret,
        ])->layout('layouts.dashboard.app');
    }
}
