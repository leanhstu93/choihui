import { describe, expect, it } from 'vitest';
import { relayLimitMessage } from './session.js';

const MB = 1024 ** 2;

describe('relayLimitMessage', () => {
    it('lets any size through on a direct connection', () => {
        expect(relayLimitMessage(5 * 1024 * MB, false, 500 * MB)).toBeNull();
    });

    it('allows a send up to the relay limit', () => {
        expect(relayLimitMessage(500 * MB, true, 500 * MB)).toBeNull();
    });

    it('asks to switch to shared wifi when one send is over the relay limit', () => {
        const message = relayLimitMessage(501 * MB, true, 500 * MB);

        expect(message).toContain('500 MB');
        expect(message).toContain('wifi');
    });
});
