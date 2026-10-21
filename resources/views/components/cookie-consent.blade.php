@if (config('services.google_analytics.measurement_id'))
    {{-- Hỏi một lần trước khi Google Analytics đặt cookie (Nghị định 13/2023). Lưu lựa chọn trên máy. --}}
    <div id="cookie-consent" hidden class="fixed inset-x-3 bottom-20 z-40 mx-auto max-w-md rounded-2xl bg-gray-900 p-4 text-sm text-white shadow-2xl sm:bottom-4" role="dialog" aria-label="Cookie thống kê">
        <p>Cho phép cookie thống kê để chúng tôi biết trang nào hữu ích? <a href="{{ route('privacy') }}" class="underline">Chi tiết</a></p>
        <div class="mt-3 flex gap-2">
            <button type="button" data-consent="granted" class="flex-1 rounded-full bg-emerald-500 py-2 font-semibold text-gray-900">Đồng ý</button>
            <button type="button" data-consent="denied" class="flex-1 rounded-full bg-white/10 py-2 font-semibold">Không</button>
        </div>
    </div>
    <script>
        (() => {
            const banner = document.getElementById('cookie-consent');
            if (! banner || localStorage.getItem('cookie-consent')) return;
            banner.hidden = false;
            banner.querySelectorAll('[data-consent]').forEach((button) => button.addEventListener('click', () => {
                const choice = button.dataset.consent;
                localStorage.setItem('cookie-consent', choice);
                gtag('consent', 'update', { analytics_storage: choice });
                banner.hidden = true;
            }));
        })();
    </script>
@endif
