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

<x-mail::button :url="route('family.index')" color="success">
Mở {{ config('app.name') }}
</x-mail::button>

Bạn có thể đổi số ngày nhắc trước của từng ngày nhớ, hoặc đặt 0 để không nhắc.
</x-mail::message>
