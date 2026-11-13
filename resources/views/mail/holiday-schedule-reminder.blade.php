<x-mail::message>
# Lịch nghỉ Tết {{ $year }} chưa có bản chính thức

Lịch âm trên {{ config('app.name') }} đang hiện lịch nghỉ Tết {{ $year }} là **dự kiến**. Khi Bộ Nội vụ đã công bố lịch nghỉ Tết Âm lịch và Quốc khánh {{ $year }}, bạn vào trang quản trị để nhập lịch chính thức.

<x-mail::button :url="route('admin.holidays')" color="success">
Nhập lịch nghỉ lễ
</x-mail::button>

Email này gửi mỗi thứ Hai cho tới khi có lịch nghỉ Tết {{ $year }}.
</x-mail::message>
