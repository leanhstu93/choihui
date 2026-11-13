/**
 * Mã xác minh an toàn: 4 hình sinh từ dấu vân tay khóa mã hóa (DTLS) của cả hai máy. Nếu có kẻ
 * chen vào giữa (kể cả server mai mối), mỗi máy thấy một khóa khác nên hai màn hình hiện hai mã
 * khác nhau. Hai người so thấy giống nhau là kết nối an toàn.
 */

/** 64 hình dễ nhận và dễ đọc to qua điện thoại; mỗi hình mang 6 bit. */
export const SAFETY_EMOJIS = [
    ['🐶', 'chó'], ['🐱', 'mèo'], ['🐭', 'chuột'], ['🐰', 'thỏ'], ['🦊', 'cáo'], ['🐻', 'gấu'], ['🐼', 'gấu trúc'], ['🐯', 'hổ'],
    ['🦁', 'sư tử'], ['🐮', 'bò'], ['🐷', 'heo'], ['🐸', 'ếch'], ['🐵', 'khỉ'], ['🐔', 'gà'], ['🐧', 'chim cánh cụt'], ['🦆', 'vịt'],
    ['🦉', 'cú'], ['🐴', 'ngựa'], ['🦄', 'kỳ lân'], ['🐝', 'ong'], ['🦋', 'bướm'], ['🐌', 'ốc sên'], ['🐢', 'rùa'], ['🐍', 'rắn'],
    ['🐙', 'bạch tuộc'], ['🦀', 'cua'], ['🐟', 'cá'], ['🐬', 'cá heo'], ['🐳', 'cá voi'], ['🍎', 'táo'], ['🍌', 'chuối'], ['🍉', 'dưa hấu'],
    ['🍇', 'nho'], ['🍓', 'dâu'], ['🍍', 'dứa'], ['🥥', 'dừa'], ['🌽', 'bắp'], ['🥕', 'cà rốt'], ['🍄', 'nấm'], ['🌵', 'xương rồng'],
    ['🌻', 'hướng dương'], ['🌹', 'hoa hồng'], ['🍀', 'cỏ bốn lá'], ['🌙', 'trăng'], ['⭐', 'ngôi sao'], ['☀️', 'mặt trời'], ['🌈', 'cầu vồng'], ['⚡', 'tia chớp'],
    ['❄️', 'bông tuyết'], ['🔥', 'lửa'], ['💧', 'giọt nước'], ['🚗', 'ô tô'], ['🚲', 'xe đạp'], ['✈️', 'máy bay'], ['🚀', 'tên lửa'], ['⛵', 'thuyền'],
    ['⚽', 'bóng đá'], ['🎸', 'đàn ghi-ta'], ['🎈', 'bóng bay'], ['🎁', 'hộp quà'], ['🔑', 'chìa khóa'], ['☂️', 'cái ô'], ['🏠', 'ngôi nhà'], ['📚', 'sách'],
];

/** Lấy dấu vân tay DTLS (dạng "sha-256 AB:CD:...") từ một SDP; null nếu không có. */
export function extractFingerprint(sdp) {
    const match = /^a=fingerprint:(\S+)\s+([0-9A-Fa-f:]+)\s*$/m.exec(String(sdp ?? ''));

    return match ? `${match[1].toLowerCase()} ${match[2].toUpperCase()}` : null;
}

/**
 * 4 hình từ hai dấu vân tay. Không phụ thuộc thứ tự truyền vào, nên hai máy ra cùng một mã.
 *
 * @returns {Promise<Array<{emoji: string, name: string}>>}
 */
export async function safetyCode(fingerprintA, fingerprintB, subtle = globalThis.crypto.subtle) {
    const input = [fingerprintA, fingerprintB].sort().join('|');
    const digest = new Uint8Array(await subtle.digest('SHA-256', new TextEncoder().encode(input)));
    // 24 bit đầu chia thành 4 nhóm 6 bit.
    const bits = (digest[0] << 16) | (digest[1] << 8) | digest[2];

    return [18, 12, 6, 0].map((shift) => {
        const [emoji, name] = SAFETY_EMOJIS[(bits >> shift) & 63];

        return { emoji, name };
    });
}
