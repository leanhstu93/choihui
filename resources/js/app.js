import { toBlob } from 'html-to-image';

/**
 * Ô nhập tiền: hiển thị 1.000.000 khi gõ, đồng bộ số nguyên (đồng) với Livewire.
 * Dùng qua component Blade <x-money-input>.
 */
const formatMoney = (value) => (value === null || value === undefined || value === '' ? '' : Number(value).toLocaleString('vi-VN'));

document.addEventListener('alpine:init', () => {
    window.Alpine.data('moneyInput', (value) => ({
        value,
        display: '',

        init() {
            this.display = formatMoney(this.value);
            this.$watch('value', (newValue) => {
                this.display = formatMoney(newValue);
            });
        },

        onInput(event) {
            const digits = event.target.value.replace(/\D/g, '');
            this.value = digits === '' ? null : Number(digits);
            this.display = formatMoney(this.value);
            event.target.value = this.display;
        },
    }));
});

/**
 * Ô nhập ngày: hiển thị dd/mm/yyyy khi gõ, đồng bộ Y-m-d với Livewire. Ngày gõ dở
 * hoặc không có thật gửi một giá trị không phải ngày để rule `date` của server báo
 * lỗi, thay vì bị coi như bỏ trống. Dùng qua component Blade <x-date-input>.
 */
const INVALID_DATE = 'ngay-khong-hop-le';

const isoToDisplay = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');

    return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
};

const displayToIso = (text) => {
    const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(text);

    if (! match) {
        return INVALID_DATE;
    }

    const [day, month, year] = match.slice(1).map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    const exists = date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;

    return exists ? `${match[3]}-${match[2]}-${match[1]}` : INVALID_DATE;
};

// Tự thêm dấu "/"; gõ "5/" hiểu là "05/" để nhập được cả ngày một chữ số.
const maskDate = (raw) => {
    const cleaned = raw.replace(/[^\d/]/g, '');
    const parts = cleaned.split('/');
    const digits = parts
        .map((part, index) => (index < 2 && index < parts.length - 1 && part.length === 1 ? `0${part}` : part))
        .join('')
        .slice(0, 8);
    const masked = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4)].filter(Boolean).join('/');

    return cleaned.endsWith('/') && (digits.length === 2 || digits.length === 4) ? `${masked}/` : masked;
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('dateInput', (value) => ({
        value,
        display: '',

        init() {
            this.display = isoToDisplay(this.value);
            this.$watch('value', (newValue) => {
                if (newValue !== INVALID_DATE) {
                    this.display = isoToDisplay(newValue);
                }
            });
        },

        get pickerValue() {
            return this.value === INVALID_DATE ? '' : (this.value ?? '');
        },

        onInput(event) {
            this.display = maskDate(event.target.value);
            event.target.value = this.display;
            this.value = this.display === '' ? '' : displayToIso(this.display);
        },

        onPick(event) {
            this.value = event.target.value;
        },

        openPicker() {
            const picker = this.$refs.picker;

            try {
                picker.showPicker();
            } catch {
                picker.focus();
                picker.click();
            }
        },
    }));
});

/**
 * Xuất một phần trang (x-ref="capture") thành ảnh PNG. Trên điện thoại mở khung
 * Chia sẻ (Zalo, Messenger, Lưu ảnh...); nếu trình duyệt không hỗ trợ thì tải file về.
 */

document.addEventListener('alpine:init', () => {
    window.Alpine.data('imageExport', (fileName) => ({
        busy: false,
        error: null,

        async createFile() {
            const blob = await toBlob(this.$refs.capture, {
                pixelRatio: 2,
                backgroundColor: '#ffffff',
                // Không nhúng font web: trình duyệt chặn đọc CSS khác nguồn (fonts.bunny.net)
                // làm hỏng ảnh. Ảnh dùng font hệ thống của máy.
                skipFonts: true,
            });

            return new File([blob], fileName, { type: 'image/png' });
        },

        async save() {
            this.busy = true;
            this.error = null;

            try {
                const file = await this.createFile();

                if (navigator.canShare?.({ files: [file] })) {
                    await navigator.share({ files: [file], title: fileName });
                } else {
                    const url = URL.createObjectURL(file);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = fileName;
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                }
            } catch (exception) {
                if (exception?.name !== 'AbortError') {
                    console.error(exception);
                    this.error = 'Không tạo được ảnh, vui lòng thử lại.';
                }
            } finally {
                this.busy = false;
            }
        },
    }));
});

/**
 * Cài app lên màn hình chính (PWA). Android: giữ sự kiện beforeinstallprompt để
 * nút "Cài app" mở hộp cài đặt của trình duyệt. iPhone: hiện hướng dẫn 3 bước,
 * vì Safari không cho trang tự mở hộp cài đặt. Trình duyệt trong Zalo / Facebook:
 * nhắc mở bằng trình duyệt. Dùng qua component Blade <x-install-app>.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.deferredInstallPrompt = event;
    window.dispatchEvent(new CustomEvent('install-available'));
});

window.addEventListener('appinstalled', () => {
    window.deferredInstallPrompt = null;
    window.gtag?.('event', 'app_installed');
});

const INSTALL_DISMISS_KEY = 'install-dismissed-until';
const INSTALL_DISMISS_DAYS = 14;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('installApp', () => ({
        platform: null,
        showGuide: false,

        init() {
            const agent = navigator.userAgent;
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
            const isIos = /iPhone|iPad|iPod/.test(agent) || (agent.includes('Macintosh') && navigator.maxTouchPoints > 1);
            const isAndroid = /Android/.test(agent);
            let dismissedUntil = 0;

            try {
                dismissedUntil = Number(localStorage.getItem(INSTALL_DISMISS_KEY) || 0);
            } catch {}

            if (isStandalone || dismissedUntil > Date.now() || (! isIos && ! isAndroid)) {
                return;
            }

            if (/Zalo|FBAN|FBAV|FB_IAB|Instagram|Line\/|TikTok|musical_ly/i.test(agent)) {
                this.platform = 'in-app';
            } else if (isIos) {
                this.platform = 'ios';
            } else {
                this.platform = window.deferredInstallPrompt ? 'android' : 'android-manual';
                window.addEventListener('install-available', () => { this.platform = 'android'; });
            }
        },

        async install() {
            if (this.platform !== 'android' || ! window.deferredInstallPrompt) {
                this.showGuide = true;

                return;
            }

            const promptEvent = window.deferredInstallPrompt;
            window.deferredInstallPrompt = null;
            promptEvent.prompt();
            const { outcome } = await promptEvent.userChoice;
            window.gtag?.('event', 'app_install_prompt', { outcome });

            if (outcome === 'accepted') {
                this.platform = null;
            }
        },

        dismiss() {
            try {
                localStorage.setItem(INSTALL_DISMISS_KEY, String(Date.now() + INSTALL_DISMISS_DAYS * 86400000));
            } catch {}

            this.platform = null;
            this.showGuide = false;
        },
    }));
});
