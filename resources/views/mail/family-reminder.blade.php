<x-mail::message>
# Chào {{ $user->name }},

@if ($events !== [])
**Ngày nhớ sắp tới**

<x-mail::table>
| Ngày | Việc | Còn |
|:--|:--|:--|
@foreach ($events as $occurrence)
| {{ $occurrence->date->format('d/m/Y') }} ({{ $occurrence->lunarLabel }}) | {{ $occurrence->event->title }}{{ $occurrence->yearsLabel ? ' · '.$occurrence->yearsLabel : '' }} | {{ $occurrence->countdown() }} |
@endforeach
</x-mail::table>
@endif

@if ($followUps->isNotEmpty())
**Lịch tái khám**

<x-mail::table>
| Ngày hẹn | Người | Lần khám trước |
|:--|:--|:--|
@foreach ($followUps as $record)
| {{ $record->follow_up_on->format('d/m/Y') }}{{ $record->follow_up_on->isSameDay($today) ? ' (hôm nay)' : '' }} | {{ $record->person->name }} | {{ $record->title }}{{ $record->place ? ' · '.$record->place : '' }} |
@endforeach
</x-mail::table>
@endif

@if ($tasks !== [])
**Việc hôm nay**

<x-mail::table>
| Hạn | Việc |
|:--|:--|
@foreach (array_slice($tasks, 0, \App\Mail\FamilyReminderMail::MAX_TASKS) as $task)
| {{ $task->dueBadge($today)['label'] ?? '' }} | {{ $task->important ? '★ ' : '' }}{{ str_replace('|', '/', $task->title) }} |
@endforeach
</x-mail::table>
@if (count($tasks) > \App\Mail\FamilyReminderMail::MAX_TASKS)
và {{ count($tasks) - \App\Mail\FamilyReminderMail::MAX_TASKS }} việc khác.
@endif
@endif

<x-mail::button :url="$events === [] && $followUps->isEmpty() ? route('tasks.index') : route('family.index')" color="success">
Mở {{ config('app.name') }}
</x-mail::button>

@if ($events !== [])
Bạn có thể đổi số ngày nhắc trước của từng ngày nhớ, hoặc đặt 0 để không nhắc.
@endif
@if ($tasks !== [])
Không muốn nhận phần việc cần làm? Tắt trong Cài đặt của {{ config('app.name') }}.
@endif
</x-mail::message>
