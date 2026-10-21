<x-mail::message>
# Chào {{ $user->name }},

Bạn có **{{ count($payments) }} khoản hụi** cần đóng, tổng **{{ number_format($total, 0, ',', '.') }} đ**.

<x-mail::table>
| Hạn đóng | Dây hụi | Kỳ · Tay | Số tiền |
|:--|:--|:--|--:|
@foreach ($payments as $payment)
| {{ $payment->dueDate->format('d/m/Y') }}{{ $payment->isOverdue($today) ? ' (quá hạn)' : '' }} | {{ $payment->groupName }} | Kỳ {{ $payment->roundNo }} · {{ $payment->slotLabel }} | {{ $payment->isEstimate ? '~' : '' }}{{ number_format($payment->amount, 0, ',', '.') }} đ |
@endforeach
</x-mail::table>

@if ($hasEstimate)
Số có dấu ~ là ước tính vì kỳ đó chưa nhập lãi thăm.
@endif

<x-mail::button :url="route('dashboard')" color="success">
Mở {{ config('app.name') }}
</x-mail::button>

Bạn có thể đổi số ngày nhắc trước hoặc tắt email nhắc trong phần Cài đặt.
</x-mail::message>
