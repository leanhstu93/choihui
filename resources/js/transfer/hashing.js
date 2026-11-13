import { createSHA256 } from 'hash-wasm';

/**
 * SHA-256 tính dần theo từng mảnh (hàm có sẵn của trình duyệt chỉ băm được cả khối trong RAM).
 * Hai bên tính trong lúc gửi/nhận, không phải đọc lại file.
 */
export async function createHasher() {
    const hasher = await createSHA256();
    hasher.init();

    return {
        update(data) {
            hasher.update(data instanceof Uint8Array ? data : new Uint8Array(data));
        },
        /** Trạng thái hiện tại (vài trăm byte) để quay lại đúng vị trí khi gửi tiếp. */
        save() {
            return hasher.save();
        },
        load(state) {
            hasher.load(state);
        },
        hex() {
            return hasher.digest('hex');
        },
    };
}
