/**
 * Mai mối qua HTTP (hosting không có WebSocket): gửi tin bắt tay lên server và hỏi tin mới mỗi
 * giây. Chỉ chạy trong lúc bắt tay; kết nối xong thì dừng và đóng phòng.
 */
export class SignalingError extends Error {
    constructor(reason, message, status = 0) {
        super(message);
        this.reason = reason;
        this.status = status;
    }
}

const NETWORK_MESSAGE = 'Không liên lạc được với máy chủ. Kiểm tra mạng rồi thử lại.';

export async function request(fetcher, url, { method = 'GET', token = null, body = null } = {}) {
    let response;

    try {
        response = await fetcher(url, {
            method,
            headers: {
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
                ...(token ? { 'X-Room-Token': token } : {}),
            },
            body: body ? JSON.stringify(body) : null,
        });
    } catch {
        throw new SignalingError('network', NETWORK_MESSAGE);
    }

    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new SignalingError(data.reason ?? 'http', data.message ?? NETWORK_MESSAGE, response.status);
    }

    return data;
}

export class Signaling {
    /**
     * @param {{ baseUrl: string, code: string, token: string, fetcher?: Function, interval?: number, maxFailures?: number, setTimer?: Function, clearTimer?: Function }} options
     */
    constructor({ baseUrl, code, token, fetcher = (...args) => fetch(...args), interval = 1000, maxFailures = 8, setTimer = (fn, ms) => setTimeout(fn, ms), clearTimer = (id) => clearTimeout(id) }) {
        this.url = `${baseUrl}/${code}`;
        this.token = token;
        this.fetcher = fetcher;
        this.interval = interval;
        this.maxFailures = maxFailures;
        this.setTimer = setTimer;
        this.clearTimer = clearTimer;
        this.after = 0;
        this.failures = 0;
        this.timer = null;
        this.running = false;
    }

    send(payload) {
        return request(this.fetcher, `${this.url}/tin`, { method: 'POST', token: this.token, body: { payload } });
    }

    /**
     * Bắt đầu hỏi tin mới. onSignal(payload) cho từng tin, onPeer() khi máy kia vừa vào phòng,
     * onError(SignalingError) khi phòng hết hạn / bị xóa hoặc mất mạng quá lâu.
     */
    start({ onSignal, onPeer = () => {}, onError = () => {}, onNearby = () => {} }) {
        this.running = true;
        this.peerSeen = false;

        const tick = async () => {
            if (! this.running || this.inFlight) {
                return;
            }
            this.inFlight = true;

            try {
                // Đang chờ và cho phép tìm máy gần: gửi kèm tên ngẫu nhiên để máy cùng mạng thấy.
                const nearby = this.nearby ? `&nearby=1&name=${encodeURIComponent(this.nearby.name)}&device=${encodeURIComponent(this.nearby.device)}` : '';
                const data = await request(this.fetcher, `${this.url}/tin?after=${this.after}${nearby}`, { token: this.token });
                this.failures = 0;

                if (data.nearby && this.nearby) {
                    onNearby(data.nearby);
                }

                if (data.peerJoined && ! this.peerSeen) {
                    this.peerSeen = true;
                    onPeer();
                }

                for (const signal of data.signals) {
                    this.after = Math.max(this.after, signal.id);
                    if (this.running) {
                        try {
                            await onSignal(signal.payload);
                        } catch (error) {
                            console.error(error);
                            throw new SignalingError('peer', 'Hai máy chưa bắt tay được với nhau. Tải lại trang trên cả hai máy rồi thử lại.');
                        }
                    }
                }
            } catch (error) {
                // Mất mạng chốc lát thì thử lại, chậm dần; phòng hết hạn / sai token thì dừng hẳn.
                if (error.reason === 'network' || error.status >= 500) {
                    this.failures++;
                    if (this.failures < this.maxFailures) {
                        this.inFlight = false;
                        this.schedule(tick, this.interval * Math.min(5, 1 + this.failures));

                        return;
                    }
                }
                this.inFlight = false;
                this.running = false;
                onError(error);

                return;
            }

            this.inFlight = false;
            this.schedule(tick, this.interval);
        };

        this.tick = tick;
        tick();
    }

    schedule(tick, delay) {
        if (this.running) {
            this.timer = this.setTimer(tick, delay);
        }
    }

    /** Hỏi server lại sau khi đã dừng vì lỗi (vd. mất mạng lúc đang nối thẳng). */
    restart() {
        if (! this.running && this.tick) {
            this.running = true;
            this.failures = 0;
            this.inFlight = false;
            this.tick();
        }
    }

    /** Đổi nhịp hỏi server; hỏi ngay một lần nếu nhịp mới nhanh hơn. */
    setPace(interval) {
        const faster = interval < this.interval;
        this.interval = interval;

        if (this.running && faster && this.tick) {
            this.clearTimer(this.timer);
            this.timer = null;
            this.tick();
        }
    }

    stop() {
        this.running = false;
        if (this.timer !== null) {
            this.clearTimer(this.timer);
            this.timer = null;
        }
    }

    /** Đóng phòng trên server (xóa mọi tin còn lại). Lỗi thì bỏ qua: phòng tự hết hạn. */
    async close() {
        this.stop();
        await request(this.fetcher, this.url, { method: 'DELETE', token: this.token }).catch(() => {});
    }
}
