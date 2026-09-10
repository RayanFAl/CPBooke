<?php

namespace App\Console\Commands;

use App\Modules\Api\SavedPassengers\Services\SavedPassengerService;
use Illuminate\Console\Command;

class PurgeExpiredPassportImagesCommand extends Command
{
    protected $signature = 'saved-passengers:purge-passport-images
                            {--days= : Override retention days from config}';

    protected $description = 'Purge retained passport scan images older than the retention window';

    public function handle(SavedPassengerService $savedPassengerService): int
    {
        $daysOption = $this->option('days');
        $days = is_numeric($daysOption) ? (int) $daysOption : null;

        $purged = $savedPassengerService->purgeExpiredPassportImages($days);

        $this->info("Purged {$purged} passport image(s).");

        return self::SUCCESS;
    }
}
