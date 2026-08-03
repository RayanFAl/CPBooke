<?php

namespace App\Console\Commands;

use App\Models\SupportMessage;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MigrateSupportAttachmentsToPrivateDisk extends Command
{
    protected $signature = 'support:migrate-attachments-to-private
        {--dry-run : Report moves without writing}
        {--keep-public : Leave a copy on the public disk after migrate}
        {--rollback : Move private-disk files that still exist on public back (restore public as source of truth)}';

    protected $description = 'Move legacy support attachments from the public disk to the private local disk.';

    public function handle(): int
    {
        if (! Schema::hasTable('support_messages') || ! Schema::hasColumn('support_messages', 'attachment_path')) {
            $this->warn('support_messages attachment columns are not available.');

            return self::SUCCESS;
        }

        if ($this->option('rollback')) {
            return $this->rollback();
        }

        return $this->migrate();
    }

    private function migrate(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $keepPublic = (bool) $this->option('keep-public');
        $public = Storage::disk('public');
        $private = Storage::disk(SupportAttachmentStorage::DISK);

        $moved = 0;
        $skipped = 0;
        $missing = 0;

        SupportMessage::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($messages) use ($dryRun, $keepPublic, $public, $private, &$moved, &$skipped, &$missing): void {
                foreach ($messages as $message) {
                    $path = (string) $message->attachment_path;

                    if ($private->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! $public->exists($path)) {
                        $missing++;
                        $this->line("Missing on public disk: message #{$message->id} ({$path})");

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("[dry-run] Would move message #{$message->id}: {$path}");
                        $moved++;

                        continue;
                    }

                    $contents = $public->get($path);

                    if ($contents === null) {
                        $missing++;

                        continue;
                    }

                    $private->put($path, $contents);

                    if (! $keepPublic) {
                        $public->delete($path);
                    }

                    $moved++;
                    $this->line("Moved message #{$message->id}: {$path}");
                }
            });

        $this->info("Moved: {$moved}, already private: {$skipped}, missing: {$missing}");

        return self::SUCCESS;
    }

    private function rollback(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk('public');
        $private = Storage::disk(SupportAttachmentStorage::DISK);

        $restored = 0;

        SupportMessage::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($messages) use ($dryRun, $public, $private, &$restored): void {
                foreach ($messages as $message) {
                    $path = (string) $message->attachment_path;

                    if ($public->exists($path) || ! $private->exists($path)) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("[dry-run] Would restore message #{$message->id}: {$path}");
                        $restored++;

                        continue;
                    }

                    $contents = $private->get($path);

                    if ($contents === null) {
                        continue;
                    }

                    $public->put($path, $contents);
                    $restored++;
                    $this->line("Restored to public message #{$message->id}: {$path}");
                }
            });

        $this->info("Restored to public: {$restored}");
        $this->warn('Rollback restores public copies only. Delete private files manually after verifying.');

        return self::SUCCESS;
    }
}
