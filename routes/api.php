<?php

declare(strict_types=1);

use App\Http\Controllers\TransferRoomController;
use Illuminate\Support\Facades\Route;

// Gửi file trực tiếp: mai mối hai thiết bị (không session, không đăng nhập).
Route::controller(TransferRoomController::class)->prefix('gui-file/phong')->name('transfer.rooms.')->group(function () {
    Route::post('/', 'store')->middleware('throttle:transfer-create')->name('store');
    Route::post('{code}/vao', 'join')->middleware('throttle:transfer-signal')->name('join');
    Route::post('{code}/tin', 'signal')->middleware('throttle:transfer-signal')->name('signal');
    Route::get('{code}/tin', 'poll')->middleware('throttle:transfer-signal')->name('poll');
    Route::post('{code}/turn', 'turn')->middleware('throttle:transfer-turn')->name('turn');
    Route::delete('{code}', 'destroy')->middleware('throttle:transfer-signal')->name('destroy');
});
