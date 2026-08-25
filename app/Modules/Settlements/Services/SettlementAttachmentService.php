<?php

namespace App\Modules\Settlements\Services;

use App\Models\Settlement;
use App\Models\SettlementAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SettlementAttachmentService
{
    public function storeUploadedFile(
        Settlement $settlement,
        UploadedFile $file,
        User $actor,
        string $kind,
        string $source = SettlementAttachment::SOURCE_UPLOAD,
    ): SettlementAttachment {
        $path = $file->store('settlements/'.$settlement->id, 'local');

        return SettlementAttachment::query()->create([
            'settlement_id' => $settlement->id,
            'kind' => $kind,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'source' => $source,
            'uploaded_by' => $actor->id,
        ]);
    }

    public function storePastedCsv(Settlement $settlement, string $csvText, User $actor): SettlementAttachment
    {
        $path = 'settlements/'.$settlement->id.'/invoice-'.now()->format('YmdHis').'.csv';
        Storage::disk('local')->put($path, $csvText);

        return SettlementAttachment::query()->create([
            'settlement_id' => $settlement->id,
            'kind' => SettlementAttachment::KIND_CSV,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'pasted-invoice.csv',
            'mime' => 'text/csv',
            'size' => strlen($csvText),
            'source' => SettlementAttachment::SOURCE_IMPORT_PASTE,
            'uploaded_by' => $actor->id,
        ]);
    }
}
