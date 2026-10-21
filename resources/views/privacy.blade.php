@php $contact = config('mail.from.address'); @endphp
<!DOCTYPE html>
<html lang="vi">
    <head>
        @include('partials.head', ['title' => 'Chính sách bảo mật'])
        <meta name="description" content="Sổ hụi thu thập dữ liệu gì, dùng vào việc gì và bạn có những quyền gì với dữ liệu của mình.">
        <link rel="canonical" href="{{ route('privacy') }}">
    </head>
    <body class="bg-white font-sans text-gray-900 antialiased">
        <header class="border-b border-gray-100">
            <div class="mx-auto flex max-w-2xl items-center justify-between px-4 py-3">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-emerald-800">
                    <img src="/icons/icon-192.png" alt="" class="h-8 w-8 rounded-lg"> Sổ hụi
                </a>
                <a href="{{ route('register') }}" class="rounded-full bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Dùng miễn phí</a>
            </div>
        </header>

        <main class="mx-auto max-w-2xl space-y-6 px-4 py-10 leading-relaxed text-gray-700 [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-gray-900 [&_li]:ml-5 [&_li]:list-disc">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Chính sách bảo mật</h1>
                <p class="mt-1 text-sm text-gray-500">Cập nhật ngày 21/10/2026</p>
            </div>

            <p>Sổ hụi là công cụ ghi sổ và tính toán hụi. Chúng tôi không giữ tiền, không tổ chức hụi và không bán dữ liệu của bạn.</p>

            <section>
                <h2>Dữ liệu chúng tôi lưu</h2>
                <ul>
                    <li><strong>Tài khoản:</strong> tên, email, mật khẩu (đã mã hóa một chiều, chúng tôi không đọc được).</li>
                    <li><strong>Sổ hụi:</strong> các dây, tay, kỳ, số tiền và ghi chú bạn tự nhập; với chủ hụi, thêm tên và số điện thoại hụi viên do bạn nhập.</li>
                    <li><strong>Cài đặt:</strong> số ngày nhắc hạn, mã PIN khóa màn hình (đã mã hóa).</li>
                </ul>
            </section>

            <section>
                <h2>Dùng vào việc gì</h2>
                <ul>
                    <li>Hiển thị sổ hụi và tính tiền cho chính bạn.</li>
                    <li>Gửi email nhắc hạn và email đặt lại mật khẩu.</li>
                </ul>
                <p class="mt-2">Mỗi tài khoản chỉ xem được dữ liệu của chính mình. Chúng tôi không dùng dữ liệu sổ hụi cho quảng cáo.</p>
            </section>

            <section>
                <h2>Thống kê truy cập</h2>
                <p>Chúng tôi dùng Google Analytics để biết trang nào được xem nhiều và có bao nhiêu người đăng ký. Cookie thống kê <strong>chỉ được đặt khi bạn bấm "Đồng ý"</strong>; nếu bạn từ chối, Google chỉ nhận tín hiệu ẩn danh, không có cookie. Chúng tôi không bật quảng cáo hay cá nhân hóa quảng cáo. Muốn đổi lựa chọn, hãy xóa dữ liệu trang web trong trình duyệt.</p>
            </section>

            <section>
                <h2>Lưu trữ và sao lưu</h2>
                <p>Dữ liệu lưu trên máy chủ, truyền qua kết nối mã hóa (HTTPS). Tài khoản hụi viên có thể tự tải bản sao lưu trong phần Cài đặt.</p>
            </section>

            <section>
                <h2>Quyền của bạn</h2>
                <ul>
                    <li>Xem, sửa mọi dữ liệu bạn đã nhập ngay trong ứng dụng.</li>
                    <li>Xóa tài khoản trong <em>Cài đặt → Tài khoản và mật khẩu</em>: toàn bộ dữ liệu của bạn bị xóa và không khôi phục được.</li>
                    <li>Hỏi đáp về dữ liệu cá nhân: gửi email tới <a href="mailto:{{ $contact }}" class="text-emerald-700 underline">{{ $contact }}</a>.</li>
                </ul>
            </section>
        </main>

        <footer class="px-4 py-6 text-center text-xs text-gray-400">© {{ date('Y') }} Sổ hụi</footer>
        <x-cookie-consent />
    </body>
</html>
