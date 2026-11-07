<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RepairRecordPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tải ảnh hóa đơn cho đúng chủ sở hữu (document/08-sua-chua.md S5). Livewire không trả
 * được file nên đây là controller.
 */
class RepairPhotoController extends Controller
{
    public function __invoke(RepairRecordPhoto $repairRecordPhoto): BinaryFileResponse
    {
        Gate::authorize('view', $repairRecordPhoto);

        $disk = Storage::disk(RepairRecordPhoto::DISK);
        abort_unless($disk->exists($repairRecordPhoto->path), 404);

        $response = response()->file($disk->path($repairRecordPhoto->path), ['X-Content-Type-Options' => 'nosniff']);

        return $response->setPrivate()->setMaxAge(3600);
    }
}
