<?php

namespace App\Console\Commands;

use App\Models\SupportMessage;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateSupportAttachmentsCommand extends Command
{
    protected $signature = 'support:migrate-attachments {--dry-run : Report moves without writing}';

    protected $description = 'Move legacy public support attachments onto the private local disk';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $skipped = 0;
        $missing = 0;

        SupportMessage::query()
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->chunkById(100, function ($messages) use ($dryRun, &$moved, &$skipped, &$missing): void {
                foreach ($messages as $message) {
                    $path = (string) $message->attachment_path;

                    if (Storage::disk(SupportAttachmentStorage::DISK)->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! Storage::disk(SupportAttachmentStorage::LEGACY_DISK)->exists($path)) {
                        $missing++;
                        $this->warn("Missing public file for message #{$message->id}: {$path}");

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("Would move message #{$message->id}: {$path}");
                        $moved++;

                        continue;
                    }

                    $contents = Storage::disk(SupportAttachmentStorage::LEGACY_DISK)->get($path);
                    Storage::disk(SupportAttachmentStorage::DISK)->put($path, $contents);
                    Storage::disk(SupportAttachmentStorage::LEGACY_DISK)->delete($path);
                    $moved++;
                }
            });

        $this->info("Moved: {$moved}, already private: {$skipped}, missing: {$missing}");

        return self::SUCCESS;
    }
}
