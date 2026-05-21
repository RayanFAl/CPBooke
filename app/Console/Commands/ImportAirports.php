<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportAirports extends Command
{
    protected $signature = 'import:airports {file}';
    protected $description = 'Import airports from an Excel file';

    public function handle()
    {
        $file = $this->argument('file');
        $rows = Excel::toArray([], $file)[0];
        $header = array_map('strtolower', $rows[0]);
        unset($rows[0]);

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $data = array_combine($header, $row);
                Airport::create([
                    'name'    => $data['name'] ?? $data['airport_name'] ?? '',
                    'code'    => $data['code'] ?? $data['iata'] ?? '',
                    'city'    => $data['city'] ?? '',
                    'country' => $data['country'] ?? '',
                ]);
            }
            DB::commit();
            $this->info('Airports imported successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
