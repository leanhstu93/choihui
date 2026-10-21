<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\UserBackup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tải file sao lưu JSON của người đang đăng nhập (FR-11).
 */
class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, UserBackup $userBackup): JsonResponse
    {
        $fileName = 'so-hui-sao-luu-'.now()->format('Y-m-d').'.json';

        return response()->json(
            $userBackup->export($request->user()),
            headers: ['Content-Disposition' => 'attachment; filename="'.$fileName.'"'],
            options: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
