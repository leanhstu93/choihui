<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\ChangeUserRole;
use App\Actions\Admin\LockUser;
use App\Actions\Admin\RecordAdminLog;
use App\Actions\Admin\SendPasswordResetLink;
use App\Actions\Admin\UnlockUser;
use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Exceptions\HuiActionException;
use App\Livewire\Concerns\AdminOnly;
use App\Models\AdminLog;
use App\Models\User;
use App\Queries\HostReport;
use App\Queries\ProfitReport;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Chi tiết một tài khoản (document/05-quan-tri.md QT-04..07): thông tin, các
 * dây kèm lời/lỗ, khóa / mở khóa, gửi email đặt lại mật khẩu, đổi vai trò.
 */
#[Layout('layouts.app')]
#[Title('Tài khoản · Quản trị')]
class UserShow extends Component
{
    use AdminOnly;

    #[Locked]
    public User $user;

    public ?string $actionError = null;

    public ?string $actionStatus = null;

    public function mount(User $user, RecordAdminLog $recordAdminLog): void
    {
        $this->user = $user;
        $recordAdminLog->handle($this->admin(), AdminAction::ViewUser, $user);
    }

    public function lock(LockUser $lockUser): void
    {
        $this->runAction(fn () => $lockUser->handle($this->admin(), $this->user), 'Đã khóa tài khoản và đăng xuất mọi phiên.');
    }

    public function unlock(UnlockUser $unlockUser): void
    {
        $this->runAction(fn () => $unlockUser->handle($this->admin(), $this->user), 'Đã mở khóa tài khoản.');
    }

    public function sendPasswordReset(SendPasswordResetLink $sendPasswordResetLink): void
    {
        $this->runAction(fn () => $sendPasswordResetLink->handle($this->admin(), $this->user), 'Đã gửi email đặt lại mật khẩu tới '.$this->user->email.'.');
    }

    public function changeRole(string $role, ChangeUserRole $changeUserRole): void
    {
        $newRole = UserRole::tryFrom($role);

        if ($newRole === null) {
            return;
        }

        $this->runAction(fn () => $changeUserRole->handle($this->admin(), $this->user, $newRole), 'Đã đổi vai trò thành '.$newRole->label().'.');
    }

    public function render(ProfitReport $profitReport, HostReport $hostReport): View
    {
        return view('livewire.admin.user-show', [
            'isHost' => $this->user->isHost(),
            'report' => $this->user->isHost() ? $hostReport->forUser($this->user) : $profitReport->forUser($this->user),
            'logs' => AdminLog::query()->where('user_id', $this->user->id)->latest('id')->limit(20)->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    private function runAction(callable $action, string $status): void
    {
        $this->reset(['actionError', 'actionStatus']);

        try {
            $action();
            $this->user->refresh();
            $this->actionStatus = $status;
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }
}
