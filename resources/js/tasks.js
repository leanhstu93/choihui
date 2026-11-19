/**
 * Sổ việc cần làm (document/10-viec-can-lam.md V8, V10, V11): đánh dấu xong tức thì, vuốt,
 * phím tắt, thanh báo có Hoàn tác. Server vẫn là nguồn đúng; lỗi mạng thì trả dòng về như cũ.
 */
const SWIPE_DISTANCE = 80;
const LEAVE_DELAY = 900;
const UNDO_MS = 6000;
const TOAST_MS = 3500;

const isTyping = (target) => target instanceof HTMLElement
    && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

document.addEventListener('alpine:init', () => {
    // Lỗi mạng / lỗi server trên trang việc: không bật hộp lỗi của Livewire, báo nhẹ và trả lại trạng thái cũ.
    window.Livewire?.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (document.querySelector('[data-task-board]') && (! status || status >= 500)) {
                preventDefault();
                window.dispatchEvent(new CustomEvent('task-error'));
                window.dispatchEvent(new CustomEvent('task-toast', { detail: { message: 'Chưa lưu được, kiểm tra mạng rồi thử lại.', tone: 'error' } }));
            }
        });
    });

    window.Alpine.data('taskRow', (id, isDone) => ({
        id,
        done: isDone,
        pending: false,
        leaving: false,
        menu: false,
        dx: 0,
        startX: null,
        startY: null,

        complete() {
            if (this.done) {
                return;
            }

            this.done = true;
            this.pending = true;
            navigator.vibrate?.(10);

            this.$wire.complete(this.id).then((result) => {
                if (! this.pending || ! result) {
                    return;
                }

                this.pending = false;
                window.dispatchEvent(new CustomEvent('task-toast', { detail: { message: result.message, undoId: this.id } }));
                setTimeout(() => {
                    this.leaving = true;
                    setTimeout(() => this.$wire.$refresh(), 200);
                }, LEAVE_DELAY);
            });
        },

        revert() {
            if (this.pending) {
                this.pending = false;
                this.done = false;
            }
        },

        touchStart(event) {
            this.startX = event.touches[0].clientX;
            this.startY = event.touches[0].clientY;
        },

        touchMove(event) {
            if (this.startX === null || this.done) {
                return;
            }

            const dx = event.touches[0].clientX - this.startX;
            const dy = event.touches[0].clientY - this.startY;

            // Cuộn dọc thì bỏ qua, chỉ vuốt ngang mới kéo dòng.
            if (this.dx === 0 && Math.abs(dy) > Math.abs(dx)) {
                this.startX = null;

                return;
            }

            if (Math.abs(dx) > 8) {
                event.preventDefault();
                this.dx = Math.max(-120, Math.min(120, dx));
            }
        },

        touchEnd() {
            if (this.dx >= SWIPE_DISTANCE) {
                this.complete();
            } else if (this.dx <= -SWIPE_DISTANCE) {
                this.menu = true;
            }

            this.dx = 0;
            this.startX = null;
        },
    }));

    window.Alpine.data('taskToasts', () => ({
        items: [],
        seq: 0,

        push(detail) {
            const item = { key: ++this.seq, message: detail.message, undoId: detail.undoId ?? null, tone: detail.tone ?? 'info' };
            this.items = [...this.items.slice(-2), item];
            setTimeout(() => this.dismiss(item.key), item.undoId ? UNDO_MS : TOAST_MS);
        },

        dismiss(key) {
            this.items = this.items.filter((item) => item.key !== key);
        },

        undo(item) {
            this.dismiss(item.key);
            this.$wire.undo(item.undoId).then(() => this.push({ message: 'Đã hoàn tác' }));
        },
    }));

    // Phím tắt trên máy tính (V10). Đang gõ trong ô nhập thì chỉ Esc có tác dụng.
    window.Alpine.data('taskBoard', () => ({
        help: false,

        rows() {
            return [...this.$root.querySelectorAll('[data-task-row]')];
        },

        currentRow() {
            return document.activeElement?.closest?.('[data-task-row]') ?? null;
        },

        moveFocus(step) {
            const rows = this.rows();

            if (rows.length === 0) {
                return;
            }

            const index = rows.indexOf(this.currentRow());
            const next = index === -1 ? (step > 0 ? 0 : rows.length - 1) : Math.min(rows.length - 1, Math.max(0, index + step));
            rows[next].focus();
            rows[next].scrollIntoView({ block: 'nearest' });
        },

        onKey(event) {
            if (event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            if (event.key === 'Escape') {
                if (this.help) {
                    this.help = false;
                } else if (this.$wire.editingId) {
                    this.$wire.close();
                } else if (isTyping(event.target)) {
                    event.target.blur();
                }

                return;
            }

            if (isTyping(event.target) || this.$wire.editingId) {
                return;
            }

            const row = this.currentRow();
            const actions = {
                n: () => this.$refs.quickAdd?.focus(),
                '/': () => this.$refs.search?.focus(),
                ArrowDown: () => this.moveFocus(1),
                j: () => this.moveFocus(1),
                ArrowUp: () => this.moveFocus(-1),
                k: () => this.moveFocus(-1),
                x: () => row?.dispatchEvent(new CustomEvent('task-complete')),
                ' ': () => row?.dispatchEvent(new CustomEvent('task-complete')),
                d: () => row?.dispatchEvent(new CustomEvent('task-menu')),
                e: () => row && this.$wire.open(row.dataset.taskId),
                Enter: () => row && this.$wire.open(row.dataset.taskId),
                '?': () => { this.help = ! this.help; },
            };
            const action = actions[event.key] ?? actions[event.key.toLowerCase()];

            if (action && (row || ! ['x', ' ', 'd', 'e', 'Enter'].includes(event.key))) {
                event.preventDefault();
                action();
            }
        },
    }));
});
