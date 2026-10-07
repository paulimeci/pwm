<?php

namespace App\Exports\Lavazho;

use Carbon\Carbon;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RaportiPergjithshemLavazho implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected $raportet;
    protected string $etiketaPeriudhes;

    public function __construct($raportet, string $etiketaPeriudhes)
    {
        $this->raportet = $raportet;
        $this->etiketaPeriudhes = $etiketaPeriudhes;
    }

    public function collection(): Enumerable
    {
        return collect($this->raportet);
    }

    public function headings(): array
    {
        return ['Data', 'Nr. Mjeteve', 'Pagesa (LEK)', 'Monedha të Tjera'];
    }

    public function map($row): array
    {
        $tjera = '-';

        if (!empty($row['monedhat_e_tjera'])) {
            $pjeset = [];
            foreach ($row['monedhat_e_tjera'] as $kodi => $vlera) {
                $pjeset[] = number_format($vlera, 2) . ' ' . $kodi;
            }
            $tjera = implode(', ', $pjeset);
        }

        return [
            Carbon::parse($row['data'])->format('d/m/Y'),
            $row['nr_mjeteve'],
            number_format($row['pagesa_lek'], 2),
            $tjera,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);

        $sheet->getStyle('A1:D' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);

        $sheet->getStyle('B2:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 14, 'C' => 16, 'D' => 32];
    }

    public function title(): string
    {
        $titulli = 'Raporti_Lavazho_' . $this->etiketaPeriudhes;
        return mb_substr($titulli, 0, 31);
    }
}
