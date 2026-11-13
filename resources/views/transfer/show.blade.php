@php
    $joinCode = (string) request()->query('ma', '');
@endphp

<x-public-layout title="Gửi file trực tiếp giữa hai thiết bị" description="Gửi file từ máy tính sang điện thoại và ngược lại ngay trên trình duyệt, không cần cài app, không cần đăng nhập. File đi thẳng giữa hai thiết bị, mã hóa đầu cuối, không lưu trên server." :canonical="route('transfer.show')">
    <x-slot:head>
        @vite('resources/js/transfer/transfer-ui.js')
        <meta name="robots" content="{{ $joinCode !== '' ? 'noindex' : 'index' }}">
    </x-slot:head>

    <div x-data="fileTransfer({ apiBase: @js(url('api/gui-file/phong')), joinCode: @js($joinCode) })"
        @dragover.window.prevent="if (phase === 'connected') dragging = true"
        @dragleave.window.prevent="if ($event.relatedTarget === null) dragging = false"
        @drop.window.prevent="dropFiles($event)"
        class="mx-auto max-w-2xl">

        <h1 class="text-2xl font-bold sm:text-3xl">Gửi file giữa hai thiết bị</h1>
        <p class="mt-2 flex items-start gap-2 rounded-2xl bg-emerald-50 p-3 text-sm text-emerald-900">
            <span aria-hidden="true">🔒</span>
            <span><b>File đi thẳng giữa hai thiết bị, được mã hóa, không lưu trên server.</b> Không cần cài app, không cần đăng nhập.</span>
        </p>

        {{-- Tạo mã / chờ máy kia / nhập mã --}}
        <section x-show="['creating', 'waiting', 'join-failed'].includes(phase)" class="mt-5 rounded-3xl bg-white p-5 shadow-sm sm:p-6">
            <div class="text-sm text-gray-500">Máy này: <b class="text-gray-800" x-text="me.name"></b> · <span x-text="me.device"></span></div>

            <template x-if="room && phase === 'waiting'">
                <div class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:items-center sm:gap-6">
                    <div class="w-44 shrink-0 rounded-2xl bg-white p-2 ring-1 ring-gray-200 sm:w-48 [&_svg]:h-auto [&_svg]:w-full" x-html="room.qrSvg" aria-label="Mã QR để vào phòng"></div>
                    <div class="min-w-0 text-center sm:text-left">
                        <div class="text-sm font-semibold text-gray-600">Mã phòng</div>
                        <div class="font-mono text-4xl font-bold tracking-[0.25em] text-emerald-800" x-text="room.code"></div>
                        <p class="mt-2 text-sm text-gray-600">Trên máy kia: <b>quét mã QR</b> bằng camera, hoặc mở <b>{{ str_replace(['https://', 'http://'], '', route('transfer.show')) }}</b> rồi nhập mã.</p>
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <button type="button" @click="copyCode()" class="rounded-full bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200" x-text="copiedCode ? 'Đã chép link ✓' : 'Chép link'"></button>
                            <span class="flex items-center gap-2 text-sm text-gray-500"><span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span> Đang chờ máy kia…</span>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="phase === 'creating'" class="mt-4 text-sm text-gray-500">Đang tạo mã…</div>

            <p x-show="room?.turnNotice && phase === 'waiting'" class="mt-4 flex items-start gap-2 rounded-2xl bg-amber-50 p-3 text-sm text-amber-900">
                <span aria-hidden="true">📶</span>
                <span x-text="room?.turnNotice"></span>
            </p>

            <div class="mt-5 border-t border-gray-100 pt-5">
                <label for="join-code" class="text-sm font-semibold text-gray-700">Có mã từ máy khác?</label>
                <form @submit.prevent="join()" class="mt-2 flex gap-2">
                    <input id="join-code" x-model="joinInput" maxlength="7" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="VD: K7P2QX"
                        class="min-w-0 flex-1 rounded-xl border-gray-300 font-mono text-xl uppercase tracking-widest">
                    <button type="submit" :disabled="joining" class="shrink-0 rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800 disabled:opacity-50" x-text="joining ? 'Đang vào…' : 'Kết nối'"></button>
                </form>
                <p x-show="message" x-text="message" class="mt-2 text-sm font-medium text-rose-700"></p>
            </div>
        </section>

        {{-- Đang kết nối --}}
        <section x-show="['connecting', 'relaying'].includes(phase)" class="mt-5 rounded-3xl bg-white p-6 text-center shadow-sm" style="display: none">
            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-emerald-200 border-t-emerald-700"></div>
            <p class="mt-3 font-semibold" x-text="phase === 'relaying' ? 'Chưa nối thẳng được, đang thử qua máy chủ trung chuyển…' : 'Đang kết nối hai máy…'"></p>
            <p class="mt-1 text-sm text-gray-500" x-text="phase === 'relaying' ? 'Dữ liệu vẫn được mã hóa, máy chủ trung chuyển không đọc được.' : 'Thường chỉ mất vài giây.'"></p>
            <p x-show="phase === 'connecting' && room?.turnNotice" class="mx-auto mt-3 max-w-md rounded-2xl bg-amber-50 p-3 text-left text-sm text-amber-900" x-text="room?.turnNotice"></p>
            <button type="button" @click="startOver()" class="mt-4 text-sm font-semibold text-gray-500 underline">Hủy, tạo mã mới</button>
        </section>

        {{-- Lỗi / mất kết nối --}}
        <section x-show="['error', 'disconnected'].includes(phase)" class="mt-5 rounded-3xl bg-white p-6 text-center shadow-sm" style="display: none">
            <div class="text-4xl" aria-hidden="true" x-text="phase === 'disconnected' ? '🔌' : '⚠️'"></div>
            <p class="mt-2 font-semibold" x-text="phase === 'disconnected' ? 'Đã ngắt kết nối' : 'Chưa kết nối được'"></p>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-600" x-text="message"></p>
            <button type="button" @click="startOver()" class="mt-4 rounded-full bg-emerald-700 px-6 py-3 font-semibold text-white hover:bg-emerald-800">Tạo mã mới</button>
        </section>

        {{-- Đã kết nối --}}
        <section x-show="['connected', 'unstable'].includes(phase)" class="mt-5 space-y-4" style="display: none">
            <div class="flex flex-wrap items-center gap-2 rounded-2xl bg-white p-4 shadow-sm">
                <span class="h-3 w-3 shrink-0 rounded-full" :class="phase === 'connected' ? 'bg-emerald-500' : 'animate-pulse bg-amber-400'"></span>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold" x-text="phase === 'connected' ? `Đã kết nối với ${peer?.name ?? 'máy kia'}` : 'Kết nối chập chờn, đang thử giữ…'"></div>
                    <div class="text-xs text-gray-500"><span x-text="peer?.device ?? ''"></span> · <span x-text="kindLabel"></span></div>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="kind === 'relay' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'" x-text="kind === 'relay' ? 'Qua trung chuyển' : (kind ? 'Trực tiếp' : '…')"></span>
                <button type="button" @click="disconnect()" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">Ngắt</button>
            </div>
            {{-- Mã xác minh an toàn: hai màn hình giống nhau là không ai chen vào giữa --}}
            <div x-show="safety" class="rounded-2xl bg-white p-4 shadow-sm">
                <button type="button" @click="safetyOpen = ! safetyOpen" class="flex w-full items-center gap-3 text-left" :aria-expanded="safetyOpen">
                    <span class="text-xl" aria-hidden="true">🛡️</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Mã an toàn</span>
                        <span class="mt-0.5 flex gap-2 text-2xl" :aria-label="`Mã an toàn: ${safetyWords}`">
                            <template x-for="(item, index) in safety ?? []" :key="index">
                                <span x-text="item.emoji" :title="item.name"></span>
                            </template>
                        </span>
                    </span>
                    <span class="shrink-0 text-xs font-semibold text-emerald-700" x-text="safetyOpen ? 'Ẩn' : 'Là gì?'"></span>
                </button>
                <div x-show="safetyOpen" class="mt-3 border-t border-gray-100 pt-3 text-sm text-gray-600">
                    <p>Nhìn màn hình máy kia (hoặc đọc qua điện thoại: <b x-text="safetyWords"></b>). <b>Giống nhau</b> là hai máy đang nói chuyện trực tiếp, không ai chen vào giữa.</p>
                    <p class="mt-2 text-rose-700">Khác nhau thì bấm <b>Ngắt</b> và tạo mã mới.</p>
                </div>
            </div>

            <p x-show="kind === 'relay'" class="rounded-2xl bg-amber-50 p-3 text-sm text-amber-900">Mạng đang chặn kết nối thẳng nên dữ liệu đi vòng qua máy chủ trung chuyển (vẫn được mã hóa, máy chủ không đọc được). Tốc độ có thể chậm hơn và mỗi lượt gửi tối đa <b x-text="formatBytes(room?.maxRelayBytes ?? 0)"></b>; dùng chung một wifi sẽ nhanh nhất và không giới hạn.</p>

            {{-- Lời mời nhận file --}}
            <template x-for="offer in offers" :key="offer.id">
                <div class="rounded-3xl bg-white p-5 shadow-sm ring-2 ring-emerald-500">
                    <div class="font-semibold">📥 <span x-text="peer?.name ?? 'Máy kia'"></span> muốn gửi cho bạn <span x-text="offer.files.length"></span> file · <span x-text="formatBytes(offer.totalSize)"></span></div>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        <template x-for="file in offer.files.slice(0, 5)" :key="file.fid">
                            <li class="flex justify-between gap-3"><span class="truncate" x-text="file.name"></span><span class="shrink-0 text-gray-500" x-text="formatBytes(file.size)"></span></li>
                        </template>
                        <li x-show="offer.files.length > 5" class="text-gray-500" x-text="`và ${offer.files.length - 5} file khác`"></li>
                    </ul>
                    <p x-show="offer.check.warning" class="mt-3 rounded-xl p-3 text-sm" :class="offer.check.ok ? 'bg-amber-50 text-amber-900' : 'bg-rose-50 text-rose-800'" x-text="offer.check.warning"></p>
                    <p class="mt-2 text-xs text-gray-500" x-text="storageNote"></p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" @click="reject(offer)" class="rounded-xl border border-gray-300 py-3 font-semibold text-gray-700 hover:bg-gray-50">Từ chối</button>
                        <button type="button" @click="accept(offer)" :disabled="! offer.check.ok || offer.busy" class="rounded-xl bg-emerald-700 py-3 font-semibold text-white hover:bg-emerald-800 disabled:opacity-40">Nhận</button>
                    </div>
                </div>
            </template>

            {{-- Gửi file --}}
            <label class="block cursor-pointer rounded-3xl border-2 border-dashed bg-white p-6 text-center shadow-sm transition" :class="dragging ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300 hover:border-emerald-400'">
                <input type="file" multiple class="sr-only" @change="pickFiles($event)">
                <div class="text-4xl" aria-hidden="true">📤</div>
                <div class="mt-2 text-lg font-semibold">Chọn file để gửi</div>
                <div class="text-sm text-gray-500"><span class="hidden sm:inline">hoặc kéo thả vào đây · </span>gửi nhiều file một lúc, <span x-text="kind === 'relay' && room?.maxRelayBytes ? `tối đa ${formatBytes(room.maxRelayBytes)} mỗi lượt` : 'không giới hạn dung lượng'"></span></div>
            </label>
            <p x-show="message && phase === 'connected'" x-text="message" class="text-sm font-medium text-rose-700"></p>

            {{-- Gửi văn bản / link --}}
            <form @submit.prevent="sendText()" class="rounded-3xl bg-white p-4 shadow-sm">
                <label for="text-draft" class="text-sm font-semibold text-gray-700">Gửi văn bản hoặc link</label>
                <textarea id="text-draft" x-model="textDraft" rows="2" maxlength="100000" placeholder="Dán nội dung vào đây…" @keydown.enter.ctrl.prevent="sendText()" @keydown.enter.meta.prevent="sendText()"
                    class="mt-2 block w-full rounded-xl border-gray-300"></textarea>
                <button type="submit" :disabled="! textDraft.trim()" class="mt-2 w-full rounded-xl bg-emerald-700 py-2.5 font-semibold text-white hover:bg-emerald-800 disabled:opacity-40 sm:w-auto sm:px-6">Gửi văn bản</button>
            </form>
        </section>

        {{-- File đã gửi / nhận --}}
        <section x-show="files.length > 0" class="mt-4 rounded-3xl bg-white p-4 shadow-sm" style="display: none">
            <h2 class="font-semibold">File</h2>
            <ul class="mt-2 divide-y divide-gray-100">
                <template x-for="entry in files" :key="entry.fid">
                    <li class="py-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-lg" :class="entry.direction === 'out' ? 'bg-sky-50' : 'bg-emerald-50'" x-text="entry.direction === 'out' ? '↑' : '↓'" :aria-label="entry.direction === 'out' ? 'Gửi đi' : 'Nhận về'"></span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium" x-text="entry.name"></div>
                                <div class="text-xs text-gray-500">
                                    <span x-text="formatBytes(entry.size)"></span> ·
                                    <span :class="{ 'text-emerald-700 font-semibold': entry.status === 'done', 'text-rose-700': ['failed', 'rejected'].includes(entry.status) }" x-text="statusLabel(entry)"></span>
                                    <span x-show="entry.note" x-text="`(${entry.note})`"></span>
                                </div>
                                <div x-show="['sending', 'receiving', 'done'].includes(entry.status)" class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full transition-all" :class="entry.status === 'done' ? 'bg-emerald-500' : 'bg-sky-500'" :style="`width: ${percent(entry)}%`"></div>
                                </div>
                                <div x-show="['sending', 'receiving'].includes(entry.status)" class="mt-1 flex justify-between text-xs text-gray-500">
                                    <span x-text="`${percent(entry)}% · ${formatBytes(entry.done)}`"></span>
                                    <span x-text="[entry.speedText, entry.etaText].filter(Boolean).join(' · ')"></span>
                                </div>
                            </div>
                            <button type="button" x-show="isActive(entry)" @click="cancel(entry)" class="shrink-0 rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">Hủy</button>
                            <button type="button" x-show="entry.status === 'done' && entry.result?.file" @click="save(entry)" class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="entry.saved ? 'bg-gray-100 text-gray-600' : 'bg-emerald-700 text-white'" x-text="entry.saved ? 'Lưu lại' : 'Lưu'"></button>
                        </div>
                    </li>
                </template>
            </ul>
        </section>

        {{-- Văn bản --}}
        <section x-show="texts.length > 0" class="mt-4 rounded-3xl bg-white p-4 shadow-sm" style="display: none">
            <h2 class="font-semibold">Văn bản</h2>
            <ul class="mt-2 space-y-2">
                <template x-for="entry in texts" :key="entry.id">
                    <li class="rounded-2xl p-3 text-sm" :class="entry.from === 'me' ? 'bg-sky-50' : 'bg-emerald-50'">
                        <div class="text-xs text-gray-500" x-text="entry.from === 'me' ? 'Bạn đã gửi' : `${peer?.name ?? 'Máy kia'} gửi`"></div>
                        <p class="mt-1 whitespace-pre-wrap break-words" x-text="entry.text"></p>
                        <div class="mt-2 flex gap-2">
                            <button type="button" @click="copyText(entry)" class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200" x-text="entry.copied ? 'Đã chép ✓' : 'Sao chép'"></button>
                            <a x-show="entry.link" :href="entry.link ? entry.text.trim() : '#'" target="_blank" rel="noopener noreferrer nofollow" class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-gray-200">Mở link</a>
                        </div>
                    </li>
                </template>
            </ul>
        </section>

        {{-- Giải thích --}}
        <section class="mt-10 space-y-3 text-sm text-gray-700">
            <h2 class="text-lg font-bold text-gray-900">Dùng thế nào?</h2>
            <ol class="list-decimal space-y-1 pl-5">
                <li>Mở trang này trên máy gửi: trang tự tạo mã phòng và mã QR.</li>
                <li>Trên máy nhận, quét mã QR (hoặc mở trang này và nhập mã).</li>
                <li>Chọn file. Máy nhận bấm <b>Nhận</b> thì file mới bắt đầu đi.</li>
            </ol>
            @foreach ([
                ['File có đi qua server không?', 'Không. Server chỉ giúp hai máy tìm thấy nhau trong vài giây đầu. File đi thẳng giữa hai máy và được mã hóa. Nếu mạng chặn kết nối thẳng, dữ liệu đi vòng qua máy chủ trung chuyển, vẫn được mã hóa, và trang sẽ báo cho bạn biết.'],
                ['Gửi được file lớn tới đâu?', 'Giữa hai máy tính dùng Chrome hoặc Edge: không giới hạn, file nhiều GB vẫn được. Firefox và điện thoại Android: file tải thẳng vào thư mục Tải về. iPhone: giới hạn theo bộ nhớ trống của máy, trang sẽ báo trước khi nhận.'],
                ['Mã phòng dùng được bao lâu?', 'Mỗi mã chỉ cho đúng 2 thiết bị, tự hết hạn sau 10 phút không dùng. Khi hai máy đã kết nối, mã bị xóa ngay.'],
            ] as [$question, $answer])
                <details class="rounded-2xl bg-white p-4 shadow-sm">
                    <summary class="cursor-pointer font-semibold text-gray-900">{{ $question }}</summary>
                    <p class="mt-2">{{ $answer }}</p>
                </details>
            @endforeach
        </section>
    </div>
</x-public-layout>
