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

class DetajetDitesLavazhoExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected array $rreshtat;
    protected string $dataEtiketa;

    public function __construct(array $rreshtat, string $dataEtiketa)
    {
        $this->rreshtat = $rreshtat;
        $this->dataEtiketa = $dataEtiketa;
    }

    public function collection(): Enumerable
    {
        return collect($this->rreshtat);
    }

    public function headings(): array
    {
        return ['Targa', 'Hyrja', 'Ikja', 'Shërbimi', 'Kategoria', 'Operatori', 'Vlera', 'Monedha', 'Paguar'];
    }

    public function map($row): array
    {
        return [
            $row['targa'],
            Carbon::parse($row['nisja'])->format('d/m/Y H:i'),
            $row['ikja'] ? Carbon::parse($row['ikja'])->format('d/m/Y H:i') : '-',
            $row['sherbimi']['sherbimi'] ?? '-',
            $row['kategoria']['kategoria'] ?? '-',
            $row['operatori']['name'] ?? '-',
            number_format((float) $row['vlera'], 2),
            $row['monedha']['kodi'] ?? '-',
            $row['pagesa'] === 'po' ? 'Po' : 'Jo',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:I1')->applyFromArray([
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

        $sheet->getStyle('A1:I' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);

        $sheet->getStyle('G2:G' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 18, 'C' => 18, 'D' => 20,
            'E' => 16, 'F' => 18, 'G' => 12, 'H' => 10, 'I' => 10,
        ];
    }

    public function title(): string
    {
        $titulli = 'Lavazho_' . str_replace('/', '-', $this->dataEtiketa);
        // Excel nuk lejon tituj sheet-i > 31 karaktere
        return mb_substr($titulli, 0, 31);
    }
}
