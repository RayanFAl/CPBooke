<?php

namespace App\Modules\Settlements\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SettlementInvoiceParser
{
    /**
     * @return array<int, array{booking_reference: string, amount: float}>
     */
    public function parseCsvText(string $csvText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvText)) ?: [];
        $result = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = str_getcsv($line);

            if (count($parts) < 2) {
                continue;
            }

            $ref = trim((string) $parts[0]);
            $amount = trim((string) $parts[1]);

            if ($index === 0 && ! is_numeric($amount)) {
                continue;
            }

            if (! is_numeric($amount)) {
                continue;
            }

            $result[] = [
                'booking_reference' => $ref,
                'amount' => (float) $amount,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{booking_reference: string, amount: float}>
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsvText((string) file_get_contents($file->getRealPath()));
        }

        if (in_array($extension, ['xlsx', 'xlsm'], true)) {
            return $this->parseXlsx((string) $file->getRealPath());
        }

        throw ValidationException::withMessages([
            'invoice_file' => 'Invoice file must be CSV or XLSX.',
        ]);
    }

    public function kindForFile(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return match ($extension) {
            'pdf' => 'pdf',
            'xlsx', 'xlsm' => 'xlsx',
            default => 'csv',
        };
    }

    /**
     * @return array<int, array{booking_reference: string, amount: float}>
     */
    private function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'invoice_file' => 'Unable to read the XLSX invoice file.',
            ]);
        }

        $shared = $this->xlsxSharedStrings($zip->getFromName('xl/sharedStrings.xml') ?: '');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml') ?: $zip->getFromName('xl/worksheets/sheet.xml');
        $zip->close();

        if (! is_string($sheet) || $sheet === '') {
            throw ValidationException::withMessages([
                'invoice_file' => 'The XLSX file does not contain a readable first sheet.',
            ]);
        }

        $rows = [];
        $xml = simplexml_load_string($sheet);

        if ($xml === false) {
            throw ValidationException::withMessages([
                'invoice_file' => 'The XLSX sheet could not be parsed.',
            ]);
        }

        foreach ($xml->sheetData->row ?? [] as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $value = '';

                if ((string) $cell['t'] === 's') {
                    $index = (int) ($cell->v ?? 0);
                    $value = $shared[$index] ?? '';
                } else {
                    $value = trim((string) ($cell->v ?? ''));
                }

                $cells[$col] = $value;
            }

            $rows[] = [
                trim((string) ($cells['A'] ?? '')),
                trim((string) ($cells['B'] ?? '')),
            ];
        }

        $csv = collect($rows)
            ->filter(fn (array $row): bool => $row[0] !== '' || $row[1] !== '')
            ->map(fn (array $row): string => $row[0].','.$row[1])
            ->implode("\n");

        return $this->parseCsvText($csv);
    }

    /**
     * @return array<int, string>
     */
    private function xlsxSharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $parsed = simplexml_load_string($xml);

        if ($parsed === false) {
            return [];
        }

        $strings = [];

        foreach ($parsed->si as $si) {
            $strings[] = trim((string) ($si->t ?? $si->r->t ?? ''));
        }

        return $strings;
    }
}
