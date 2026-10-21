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
