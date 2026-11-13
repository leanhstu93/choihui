/**
 * Giao thức giữa hai thiết bị trên một DataChannel có thứ tự: chuỗi JSON là tin điều khiển,
 * ArrayBuffer là một mảnh của file đang truyền (file nào thì theo tin "start" gần nhất).
 * Dùng một kênh duy nhất để tin điều khiển và dữ liệu không bao giờ đến lệch thứ tự.
 */
/** Mảnh lớn nhất; dùng nhỏ hơn nếu kết nối báo giới hạn thấp hơn (sctp.maxMessageSize). */
export const CHUNK_SIZE = 256 * 1024;

/** Đọc file theo khối 1 MB rồi chia mảnh: ít lần gọi đĩa hơn đọc từng 64 KB. */
export const READ_BLOCK = 1024 * 1024;

/** Bên gửi không vượt quá phần bên nhận đã ghi xong cộng ngần này byte. Giới hạn RAM hai bên. */
export const SEND_WINDOW = 16 * 1024 * 1024;

/** Bên nhận báo đã ghi xong sau mỗi ngần này byte. */
export const ACK_EVERY = 1024 * 1024;

/** Bộ đệm của DataChannel: dừng đẩy khi vượt HIGH, đẩy tiếp khi xuống dưới LOW. */
export const BUFFER_HIGH = 4 * 1024 * 1024;
export const BUFFER_LOW = 1024 * 1024;

/** Văn bản / link gửi qua kênh tối đa 100 000 ký tự. */
export const MAX_TEXT = 100000;

const MESSAGE_TYPES = new Set(['hello', 'text', 'offer', 'accept', 'reject', 'withdraw', 'start', 'end', 'ack', 'done', 'cancel', 'bye', 'resume']);

/**
 * Đọc tin điều khiển; trả null nếu không hợp lệ (máy kia gửi rác thì bỏ qua, không làm vỡ trang).
 */
export function parseMessage(text) {
    let message;

    try {
        message = JSON.parse(text);
    } catch {
        return null;
    }

    if (message === null || typeof message !== 'object' || ! MESSAGE_TYPES.has(message.t)) {
        return null;
    }

    if (message.t === 'offer') {
        const valid = typeof message.id === 'string' && Array.isArray(message.files) && message.files.length > 0
            && message.files.every((file) => typeof file.fid === 'string' && typeof file.name === 'string' && Number.isSafeInteger(file.size) && file.size >= 0);

        return valid ? message : null;
    }

    if (message.t === 'text') {
        return typeof message.text === 'string' && message.text.length <= MAX_TEXT ? message : null;
    }

    return message;
}

/** Tên file an toàn để lưu: bỏ đường dẫn và ký tự cấm, giới hạn độ dài. */
export function safeFileName(name) {
    const base = String(name).split(/[\\/]/).pop().replace(/[\u0000-\u001f<>:"|?*]/g, '_').trim();
    const cleaned = base.replace(/^\.+/, '') || 'file';

    return cleaned.length > 200 ? cleaned.slice(0, 200) : cleaned;
}

export function randomId() {
    const bytes = new Uint8Array(9);
    crypto.getRandomValues(bytes);

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}
