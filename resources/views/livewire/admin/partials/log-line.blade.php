<div class="flex items-start justify-between gap-2">
    <div class="min-w-0">
        <span class="font-medium">{{ $log->action->label() }}</span>
        @if ($log->action === \App\Enums\AdminAction::ViewGroup && isset($log->meta['group_name']))
            <span class="text-gray-600">"{{ $log->meta['group_name'] }}"</span>
        @elseif ($log->action === \App\Enums\AdminAction::ChangeRole && isset($log->meta['to']))
            <span class="text-gray-600">→ {{ \App\Enums\UserRole::tryFrom($log->meta['to'])?->label() }}</span>
        @elseif (in_array($log->action, [\App\Enums\AdminAction::SaveHolidayBreak, \App\Enums\AdminAction::DeleteHolidayBreak], true) && isset($log->meta['kind']))
            <span class="text-gray-600">{{ \App\Enums\HolidayBreakKind::tryFrom($log->meta['kind'])?->label() }} {{ $log->meta['year'] ?? '' }}@isset($log->meta['starts_on']): {{ \Carbon\CarbonImmutable::parse($log->meta['starts_on'])->format('d/m') }} – {{ \Carbon\CarbonImmutable::parse($log->meta['ends_on'])->format('d/m') }}@endisset</span>
        @endif
        @if ($showUser && ! in_array($log->action, [\App\Enums\AdminAction::SaveHolidayBreak, \App\Enums\AdminAction::DeleteHolidayBreak], true))
            <div class="truncate text-gray-500">
                @if ($log->user)
                    <a href="{{ route('admin.users.show', $log->user) }}" wire:navigate class="text-emerald-700">{{ $log->user->name }} · {{ $log->user->email }}</a>
                @else
                    Tài khoản đã xóa
                @endif
            </div>
        @endif
        <div class="truncate text-xs text-gray-400">bởi {{ $log->admin_email }}</div>
    </div>
    <time class="shrink-0 text-xs text-gray-500" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m/Y H:i') }}</time>
</div>
