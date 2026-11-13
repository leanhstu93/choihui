import { describe, expect, it } from 'vitest';
import { checkCapacity, chooseStorage, detectEnvironment, formatBytes, formatDuration, MEMORY_LIMIT, SpeedMeter } from './storage.js';
import { safeFileName } from './protocol.js';
import { connectionKind } from './stats.js';

const GB = 1024 ** 3;

describe('choosing where to write received files', () => {
    it('prefers direct file writing on desktop, streaming elsewhere and OPFS on iPhone', () => {
        expect(chooseStorage({ hasFsAccess: true, isMobile: false, hasServiceWorker: true, hasOpfs: true })).toBe('fs');
        expect(chooseStorage({ hasFsAccess: false, isMobile: false, hasServiceWorker: true, hasOpfs: true })).toBe('stream');
        expect(chooseStorage({ hasFsAccess: true, isMobile: true, isIos: false, hasServiceWorker: true, hasOpfs: true })).toBe('stream');
        expect(chooseStorage({ hasFsAccess: false, isMobile: true, isIos: true, hasServiceWorker: true, hasOpfs: true })).toBe('opfs');
        expect(chooseStorage({ hasFsAccess: false, isMobile: false, hasServiceWorker: false, hasOpfs: false })).toBe('memory');
    });

    it('detects iPhone and iPad (desktop mode) from the browser', () => {
        expect(detectEnvironment({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)', maxTouchPoints: 5 }, {}).isIos).toBe(true);
        expect(detectEnvironment({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', maxTouchPoints: 5 }, {}).isIos).toBe(true);
        expect(detectEnvironment({ userAgent: 'Mozilla/5.0 (Linux; Android 14)', maxTouchPoints: 5 }, {}).isAndroid).toBe(true);
    });
});

describe('warning before accepting', () => {
    it('refuses big files when only memory is available', () => {
        expect(checkCapacity({ storage: 'memory', totalSize: MEMORY_LIMIT + 1 }).ok).toBe(false);
        expect(checkCapacity({ storage: 'memory', totalSize: 1000 }).ok).toBe(true);
    });

    it('needs twice the size on iPhone and explains why', () => {
        const estimate = { quota: 6 * GB, usage: 1 * GB };
        const tooBig = checkCapacity({ storage: 'opfs', totalSize: 3 * GB, estimate, isIos: true });

        expect(tooBig.ok).toBe(false);
        expect(tooBig.warning).toContain('gấp đôi');
        expect(checkCapacity({ storage: 'opfs', totalSize: 2 * GB, estimate, isIos: true })).toMatchObject({ ok: true });
        expect(checkCapacity({ storage: 'opfs', totalSize: 2 * GB, estimate, isIos: true }).warning).toContain('Giữ màn hình sáng');
    });

    it('has no limit when writing straight to disk', () => {
        expect(checkCapacity({ storage: 'fs', totalSize: 50 * GB })).toEqual({ ok: true, warning: null });
    });
});

describe('formatting', () => {
    it('shows sizes, durations and speed in Vietnamese', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(5 * GB)).toBe('5 GB');
        expect(formatBytes(1536 * 1024)).toBe('1,5 MB');
        expect(formatDuration(42)).toBe('42 giây');
        expect(formatDuration(600)).toBe('10 phút');
        expect(formatDuration(3900)).toBe('1 giờ 5 phút');

        let now = 0;
        const meter = new SpeedMeter(3000, () => now);
        meter.update(0);
        now = 1000;
        meter.update(10 * 1024 * 1024);
        expect(meter.bytesPerSecond).toBe(10 * 1024 * 1024);
    });

    it('cleans file names coming from the other device', () => {
        expect(safeFileName('../../etc/passwd')).toBe('passwd');
        expect(safeFileName('C:\\Users\\a\\b<c>.txt')).toBe('b_c_.txt');
        expect(safeFileName('...')).toBe('file');
    });
});

describe('connection kind', () => {
    const reports = (localType, remoteType) => [
        { id: 'T', type: 'transport', selectedCandidatePairId: 'P' },
        { id: 'P', type: 'candidate-pair', localCandidateId: 'L', remoteCandidateId: 'R', state: 'succeeded' },
        { id: 'L', type: 'local-candidate', candidateType: localType },
        { id: 'R', type: 'remote-candidate', candidateType: remoteType },
    ];

    it('tells same network, internet and relay apart', () => {
        expect(connectionKind(reports('host', 'host'))).toBe('lan');
        expect(connectionKind(reports('srflx', 'prflx'))).toBe('direct');
        expect(connectionKind(reports('relay', 'srflx'))).toBe('relay');
        expect(connectionKind([])).toBeNull();
    });
});
