import { describe, expect, it, vi } from 'vitest';
import { Signaling } from './signaling.js';

const json = (status, body) => ({ ok: status < 400, status, json: async () => body });

/** Server giả: trả lần lượt các phản hồi đã xếp sẵn. */
function fakeServer(responses) {
    const calls = [];
    const fetcher = vi.fn(async (url, init) => {
        calls.push([init.method, url]);
        const next = responses.shift();
        if (next instanceof Error) {
            throw next;
        }

        return next ?? json(200, { signals: [], peerJoined: true });
    });

    return { fetcher, calls };
}

function manualTimers() {
    const queue = [];

    return {
        setTimer: (fn) => { queue.push(fn); return queue.length; },
        clearTimer: () => {},
        async tick() {
            const fn = queue.shift();
            await fn?.();
            await new Promise((resolve) => setTimeout(resolve, 0));
        },
    };
}

describe('Signaling polling', () => {
    it('delivers each signal once, moves the cursor and reports the peer joining', async () => {
        const { fetcher, calls } = fakeServer([
            json(200, { signals: [], peerJoined: false }),
            json(200, { signals: [{ id: 5, payload: { type: 'answer' } }, { id: 6, payload: { type: 'candidate' } }], peerJoined: true }),
            json(200, { signals: [], peerJoined: true }),
        ]);
        const timers = manualTimers();
        const signaling = new Signaling({ baseUrl: '/api/gui-file/phong', code: 'ABCDEF', token: 't', fetcher, ...timers });
        const received = [];
        const onPeer = vi.fn();

        signaling.start({ onSignal: (payload) => received.push(payload.type), onPeer });
        await new Promise((resolve) => setTimeout(resolve, 0));
        await timers.tick();
        await timers.tick();

        expect(received).toEqual(['answer', 'candidate']);
        expect(onPeer).toHaveBeenCalledTimes(1);
        expect(calls[2][1]).toBe('/api/gui-file/phong/ABCDEF/tin?after=6');
        signaling.stop();
    });

    it('retries through a short network outage, then keeps going', async () => {
        const { fetcher } = fakeServer([new TypeError('offline'), new TypeError('offline'), json(200, { signals: [{ id: 1, payload: { a: 1 } }], peerJoined: true })]);
        const timers = manualTimers();
        const onError = vi.fn();
        const received = [];
        const signaling = new Signaling({ baseUrl: '/x', code: 'C', token: 't', fetcher, ...timers });

        signaling.start({ onSignal: (payload) => received.push(payload), onError });
        await new Promise((resolve) => setTimeout(resolve, 0));
        await timers.tick();
        await timers.tick();

        expect(onError).not.toHaveBeenCalled();
        expect(received).toEqual([{ a: 1 }]);
        signaling.stop();
    });

    it('stops with a clear message when the room has expired', async () => {
        const { fetcher } = fakeServer([json(410, { reason: 'expired', message: 'Mã phòng đã hết hạn.' })]);
        const timers = manualTimers();
        const onError = vi.fn();
        const signaling = new Signaling({ baseUrl: '/x', code: 'C', token: 't', fetcher, ...timers });

        signaling.start({ onSignal: () => {}, onError });
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(onError).toHaveBeenCalledWith(expect.objectContaining({ reason: 'expired', message: 'Mã phòng đã hết hạn.' }));
        expect(signaling.running).toBe(false);
    });

    it('gives up after too many network failures', async () => {
        const { fetcher } = fakeServer(Array.from({ length: 5 }, () => new TypeError('offline')));
        const timers = manualTimers();
        const onError = vi.fn();
        const signaling = new Signaling({ baseUrl: '/x', code: 'C', token: 't', fetcher, maxFailures: 3, ...timers });

        signaling.start({ onSignal: () => {}, onError });
        await new Promise((resolve) => setTimeout(resolve, 0));
        await timers.tick();
        await timers.tick();

        expect(onError).toHaveBeenCalledWith(expect.objectContaining({ reason: 'network' }));
    });
});
