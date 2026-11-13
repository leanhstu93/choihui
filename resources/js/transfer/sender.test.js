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

        const channel = new FakeChannel((data) => {
            if (typeof data === 'string') {
                const message = parseMessage(data);
                control.push(message.t);
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
        const { file: received } = await incoming.finish();

        expect(new Uint8Array(await received.arrayBuffer())).toEqual(bytes);
        expect(control).toEqual(['start', 'end']);
        expect(maxInFlight).toBeLessThanOrEqual(256 * 1024);
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
