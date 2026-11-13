import { describe, expect, it } from 'vitest';
import { IncomingFile } from './chunker.js';
import { MemorySink } from './sinks.js';
import { OutgoingFile } from './sender.js';
import { parseMessage } from './protocol.js';

/** DataChannel giả: đưa tin sang bên kia ở lượt sau, có bufferedAmount như thật. */
class FakeChannel extends EventTarget {
    constructor(deliver) {
        super();
        this.readyState = 'open';
        this.bufferedAmount = 0;
        this.bufferedAmountLowThreshold = 0;
        this.deliver = deliver;
        this.maxBuffered = 0;
    }

    send(data) {
        if (this.readyState !== 'open') {
            throw new Error('closed');
        }
        const size = typeof data === 'string' ? data.length : data.byteLength;
        this.bufferedAmount += size;
        this.maxBuffered = Math.max(this.maxBuffered, this.bufferedAmount);
        setTimeout(() => {
            this.bufferedAmount -= size;
            if (this.bufferedAmount <= this.bufferedAmountLowThreshold) {
                this.dispatchEvent(new Event('bufferedamountlow'));
            }
            this.deliver(data);
        }, 0);
    }

    close() {
        this.readyState = 'closed';
        this.dispatchEvent(new Event('close'));
    }
}

const sha256Hex = async (bytes) => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', bytes)), (b) => b.toString(16).padStart(2, '0')).join('');

const randomFile = (size) => {
    const bytes = new Uint8Array(size);
    for (let i = 0; i < size; i += 65536) {
        crypto.getRandomValues(bytes.subarray(i, Math.min(size, i + 65536)));
    }

    return { bytes, file: new Blob([bytes]) };
};

describe('sending a file end to end', () => {
    it('rebuilds an identical file and never exceeds the send window', async () => {
        const { bytes, file } = randomFile(3 * 1024 * 1024 + 123);
        const sink = new MemorySink('test.bin');
        let incoming = null;
        let outgoing = null;
        let maxInFlight = 0;
        const control = [];
        let endMessage = null;

        const channel = new FakeChannel((data) => {
            if (typeof data === 'string') {
                const message = parseMessage(data);
                control.push(message.t);
                if (message.t === 'end') {
                    endMessage = message;
                }
                if (message.t === 'start') {
                    incoming = new IncomingFile({ size: message.size, sink, ackEvery: 64 * 1024, onAck: (written) => setTimeout(() => outgoing.ack(written), 0) });
                }
            } else {
                incoming.push(data);
            }
        });

        outgoing = new OutgoingFile({ file, fid: 'f1', channel, chunkSize: 16 * 1024, window: 256 * 1024 });
        const originalAdd = outgoing.window.add.bind(outgoing.window);
        outgoing.window.add = (n) => {
            originalAdd(n);
            maxInFlight = Math.max(maxInFlight, outgoing.window.inFlight);
        };

        expect(await outgoing.run()).toBe(true);
        await new Promise((resolve) => setTimeout(resolve, 10));
        const { file: received, verified, sha256 } = await incoming.finish(endMessage.sha256);

        expect(new Uint8Array(await received.arrayBuffer())).toEqual(bytes);
        expect(verified).toBe(true);
        expect(sha256).toBe(await sha256Hex(bytes));
        expect(control).toEqual(['start', 'end']);
        expect(maxInFlight).toBeLessThanOrEqual(256 * 1024);
    });

    it('reports a mismatch when a byte is changed on the way', async () => {
        const { file } = randomFile(300 * 1024);
        let incoming = null;
        let outgoing = null;
        let endMessage = null;
        let corrupted = false;

        const channel = new FakeChannel((data) => {
            if (typeof data === 'string') {
                const message = parseMessage(data);
                if (message.t === 'start') {
                    incoming = new IncomingFile({ size: message.size, sink: new MemorySink('x.bin'), ackEvery: 64 * 1024, onAck: (written) => setTimeout(() => outgoing.ack(written), 0) });
                }
                if (message.t === 'end') {
                    endMessage = message;
                }
            } else {
                if (! corrupted) {
                    new Uint8Array(data)[10] ^= 0xff;
                    corrupted = true;
                }
                incoming.push(data);
            }
        });

        outgoing = new OutgoingFile({ file, fid: 'f1', channel, chunkSize: 16 * 1024, window: 256 * 1024 });
        await outgoing.run();
        await new Promise((resolve) => setTimeout(resolve, 10));

        expect((await incoming.finish(endMessage.sha256)).verified).toBe(false);
    });

    it('resumes from the receiver offset after the connection drops, with a matching hash', async () => {
        const { bytes, file } = randomFile(2 * 1024 * 1024 + 777);
        const sink = new MemorySink('resume.bin');
        let incoming = null;
        let outgoing = null;
        let endMessage = null;
        let starts = [];
        let firstDead = false;

        const receive = (data) => {
            if (typeof data === 'string') {
                const message = parseMessage(data);
                if (message.t === 'start') {
                    starts.push(message.offset);
                    incoming ??= new IncomingFile({ size: message.size, sink, ackEvery: 64 * 1024, onAck: (written) => setTimeout(() => outgoing.ack(written), 0) });
                }
                if (message.t === 'end') {
                    endMessage = message;
                }
            } else {
                incoming.push(data);
            }
        };

        // Kênh 1 rớt sau khoảng 40%: những gì đang trên đường bị mất.
        let delivered = 0;
        const first = new FakeChannel((data) => {
            if (firstDead) {
                return;
            }
            receive(data);
            delivered += typeof data === 'string' ? 0 : data.byteLength;
            if (delivered > 0.4 * bytes.length) {
                firstDead = true;
                outgoing.pause();
                first.close();
            }
        });

        outgoing = new OutgoingFile({ file, fid: 'f1', channel: first, chunkSize: 16 * 1024, window: 256 * 1024 });
        expect(await outgoing.run(first, 0)).toBe('paused');

        const offset = await incoming.settle();
        expect(offset).toBeGreaterThan(0);
        expect(outgoing.canResumeAt(offset)).toBe(true);

        const second = new FakeChannel(receive);
        expect(await outgoing.run(second, offset)).toBe(true);
        await new Promise((resolve) => setTimeout(resolve, 10));
        const result = await incoming.finish(endMessage.sha256);

        expect(starts).toEqual([0, offset]);
        expect(result.verified).toBe(true);
        expect(new Uint8Array(await result.file.arrayBuffer())).toEqual(bytes);
    });

    it('stops sending when cancelled mid-way', async () => {
        const { file } = randomFile(2 * 1024 * 1024);
        let chunks = 0;
        const channel = new FakeChannel((data) => {
            if (typeof data !== 'string') {
                chunks++;
            }
        });
        // Không ai xác nhận: bên gửi phải dừng ở cửa sổ, rồi hủy.
        const outgoing = new OutgoingFile({ file, fid: 'f1', channel, chunkSize: 16 * 1024, window: 64 * 1024 });
        const running = outgoing.run();
        await new Promise((resolve) => setTimeout(resolve, 20));
        outgoing.cancel();

        expect(await running).toBe(false);
        expect(chunks).toBeLessThanOrEqual(4);
    });

    it('fails clearly when the connection drops', async () => {
        const { file } = randomFile(1024 * 1024);
        let outgoing = null;
        const channel = new FakeChannel((data) => {
            if (typeof data !== 'string') {
                channel.close();
            }
        });
        outgoing = new OutgoingFile({ file, fid: 'f1', channel, chunkSize: 16 * 1024, window: 10 * 1024 * 1024 });

        await expect(outgoing.run()).rejects.toThrow();
    });
});

describe('protocol messages', () => {
    it('accepts valid messages and drops junk', () => {
        expect(parseMessage('{"t":"offer","id":"a","files":[{"fid":"x","name":"a.txt","size":3}]}')).toMatchObject({ t: 'offer' });
        expect(parseMessage('{"t":"offer","id":"a","files":[{"fid":"x","name":"a.txt","size":-1}]}')).toBeNull();
        expect(parseMessage('{"t":"evil"}')).toBeNull();
        expect(parseMessage('not json')).toBeNull();
        expect(parseMessage(JSON.stringify({ t: 'text', text: 'x'.repeat(100001) }))).toBeNull();
    });
});
