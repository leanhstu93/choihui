<?php

declare(strict_types=1);

use App\Http\Controllers\BackupDownloadController;
use App\Livewire\Dashboard;
use App\Livewire\Groups\GroupForm;
use App\Livewire\Groups\GroupIndex;
use App\Livewire\Groups\GroupShow;
use App\Livewire\Groups\GroupStatement;
use App\Livewire\Reports\ProfitReportPage;
use App\Livewire\Rounds\RoundShow;
use App\Livewire\Settings\SettingsPage;
use App\Livewire\Settings\UnlockPage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('unlock', UnlockPage::class)->name('unlock');

    Route::middleware('pin')->group(function () {
        Route::get('dashboard', Dashboard::class)->middleware('verified')->name('dashboard');
        Route::view('profile', 'profile')->name('profile');

        Route::get('groups', GroupIndex::class)->name('groups.index');
        Route::get('groups/create', GroupForm::class)->name('groups.create');
        Route::get('groups/{group}', GroupShow::class)->name('groups.show');
        Route::get('groups/{group}/edit', GroupForm::class)->name('groups.edit');
        Route::get('groups/{group}/statement', GroupStatement::class)->name('groups.statement');
        Route::get('rounds/{round}', RoundShow::class)->name('rounds.show');

        Route::get('reports', ProfitReportPage::class)->name('reports');
        Route::get('settings', SettingsPage::class)->name('settings');
        Route::get('settings/backup', BackupDownloadController::class)->name('settings.backup');
    });
});

require __DIR__.'/auth.php';
