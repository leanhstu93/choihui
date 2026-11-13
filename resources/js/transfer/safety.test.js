import { describe, expect, it } from 'vitest';
import { extractFingerprint, SAFETY_EMOJIS, safetyCode } from './safety.js';

const SENDER = 'sha-256 AA:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF';
const RECEIVER = 'sha-256 BB:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF';
const ATTACKER = 'sha-256 CC:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF';

describe('safety code', () => {
    it('has 64 distinct pictures', () => {
        expect(new Set(SAFETY_EMOJIS.map(([emoji]) => emoji)).size).toBe(64);
    });

    it('reads the DTLS fingerprint from an SDP', () => {
        const sdp = 'v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\na=fingerprint:SHA-256 aa:bb:cc\r\na=setup:actpass\r\n';

        expect(extractFingerprint(sdp)).toBe('sha-256 AA:BB:CC');
        expect(extractFingerprint('v=0\r\n')).toBeNull();
    });

    it('gives both devices the same 4 pictures', async () => {
        const onSender = await safetyCode(SENDER, RECEIVER);
        const onReceiver = await safetyCode(RECEIVER, SENDER);

        expect(onSender).toHaveLength(4);
        expect(onSender).toEqual(onReceiver);
    });

    it('differs on the two screens when someone sits in the middle', async () => {
        // Kẻ chen giữa nói chuyện với mỗi bên bằng khóa của mình.
        const onSender = await safetyCode(SENDER, ATTACKER);
        const onReceiver = await safetyCode(ATTACKER, RECEIVER);

        expect(onSender).not.toEqual(onReceiver);
    });
});
