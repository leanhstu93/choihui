<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Backup\RestoreBackup;
use App\Exceptions\HuiActionException;
use App\Http\Middleware\EnsurePinUnlocked;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Cài đặt: số ngày nhắc trước hạn (FR-09), mã PIN khóa màn hình (FR-13), sao lưu / khôi phục (FR-11).
 */
#[Layout('layouts.app')]
#[Title('Cài đặt')]
class SettingsPage extends Component
{
    use WithFileUploads;

    public int $reminderDaysBefore = 3;

    public string $currentPin = '';

    public string $newPin = '';

    public string $newPinConfirmation = '';

    /** @var TemporaryUploadedFile|null */
    public $backupFile = null;

    public bool $confirmRestore = false;

    public function mount(): void
    {
        $this->reminderDaysBefore = $this->user()->reminder_days_before;
    }

    public function saveReminder(): void
    {
        $validated = $this->validate(
            ['reminderDaysBefore' => ['required', 'integer', 'min:0', 'max:30']],
            attributes: ['reminderDaysBefore' => 'số ngày nhắc trước'],
        );

        $this->user()->update(['reminder_days_before' => $validated['reminderDaysBefore']]);
        session()->flash('status', 'Đã lưu cài đặt nhắc hạn.');
    }

    public function savePin(): void
    {
        $user = $this->user();

        $this->validate([
            'currentPin' => $user->pin_hash === null ? ['nullable'] : ['required', 'string'],
            'newPin' => ['required', 'digits_between:4,6', 'same:newPinConfirmation'],
        ], [
            'newPin.digits_between' => 'Mã PIN gồm 4 đến 6 chữ số.',
            'newPin.same' => 'Hai lần nhập mã PIN không khớp.',
        ], ['currentPin' => 'mã PIN hiện tại', 'newPin' => 'mã PIN mới']);

        if (! $this->currentPinMatches($user)) {
            return;
        }

        $user->forceFill(['pin_hash' => $this->newPin])->save();
        EnsurePinUnlocked::markUnlocked();
        $this->reset(['currentPin', 'newPin', 'newPinConfirmation']);
        session()->flash('status', 'Đã lưu mã PIN.');
    }

    public function removePin(): void
    {
        $user = $this->user();

        if ($user->pin_hash === null || ! $this->currentPinMatches($user)) {
            return;
        }

        $user->forceFill(['pin_hash' => null])->save();
        $this->reset(['currentPin', 'newPin', 'newPinConfirmation']);
        session()->flash('status', 'Đã tắt khóa PIN.');
    }

    public function restoreBackup(RestoreBackup $restoreBackup): void
    {
        $this->validate([
            'backupFile' => ['required', 'file', 'max:5120'],
            'confirmRestore' => ['accepted'],
        ], [
            'backupFile.required' => 'Vui lòng chọn file sao lưu.',
            'confirmRestore.accepted' => 'Vui lòng xác nhận thay thế dữ liệu hiện tại.',
        ], ['backupFile' => 'file sao lưu']);

        $data = json_decode((string) $this->backupFile->get(), true);

        if (! is_array($data)) {
            $this->addError('backupFile', 'File không phải file sao lưu JSON của Sổ hụi.');

            return;
        }

        try {
            $stats = $restoreBackup->handle($this->user(), $data);
        } catch (HuiActionException $exception) {
            $this->addError('backupFile', $exception->getMessage());

            return;
        }

        $this->reset(['backupFile', 'confirmRestore']);
        $this->reminderDaysBefore = $this->user()->refresh()->reminder_days_before;
        session()->flash('status', "Đã khôi phục {$stats['groups']} dây hụi, {$stats['rounds']} kỳ, {$stats['entries']} dòng tiền.");
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.settings.settings-page', ['hasPin' => $this->user()->pin_hash !== null]);
    }

    private function currentPinMatches(User $user): bool
    {
        if ($user->pin_hash === null || Hash::check($this->currentPin, $user->pin_hash)) {
            return true;
        }

        $this->addError('currentPin', 'Mã PIN hiện tại không đúng.');

        return false;
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
