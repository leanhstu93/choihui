<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HealthRecordPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tải ảnh sổ sức khỏe cho đúng chủ sở hữu (document/09-gia-dinh.md G11). Livewire không trả
 * được file nên đây là controller.
 */
class HealthPhotoController extends Controller
{
    public function __invoke(HealthRecordPhoto $healthRecordPhoto): BinaryFileResponse
    {
        Gate::authorize('view', $healthRecordPhoto);

        $disk = Storage::disk(HealthRecordPhoto::DISK);
        abort_unless($disk->exists($healthRecordPhoto->path), 404);

        $response = response()->file($disk->path($healthRecordPhoto->path), ['X-Content-Type-Options' => 'nosniff']);

        return $response->setPrivate()->setMaxAge(3600);
    }
}
