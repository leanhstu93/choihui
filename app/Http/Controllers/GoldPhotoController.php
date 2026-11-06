<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GoldItemPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tải ảnh món vàng cho đúng chủ sở hữu (document/06-vang.md V13). Livewire không trả
 * được file nên đây là controller.
 */
class GoldPhotoController extends Controller
{
    public function __invoke(GoldItemPhoto $goldItemPhoto): BinaryFileResponse
    {
        Gate::authorize('view', $goldItemPhoto);

        $disk = Storage::disk(GoldItemPhoto::DISK);
        abort_unless($disk->exists($goldItemPhoto->path), 404);

        $response = response()->file($disk->path($goldItemPhoto->path), ['X-Content-Type-Options' => 'nosniff']);

        return $response->setPrivate()->setMaxAge(3600);
    }
}
