import { describe, expect, it } from 'vitest';
import { folderName, safePathSegments, ZipAssembler } from './folder.js';
import { MemorySink } from './sinks.js';

describe('folder paths from the other device', () => {
    it('keeps sub-folders and drops anything that climbs out', () => {
        expect(safePathSegments('Ảnh/2024/a.jpg')).toEqual(['Ảnh', '2024', 'a.jpg']);
        expect(safePathSegments('../../etc/passwd')).toEqual(['etc', 'passwd']);
        expect(safePathSegments('/abs\\\\win\\x.txt')).toEqual(['abs', 'win', 'x.txt']);
        expect(safePathSegments('a/<b>/c?.txt')).toEqual(['a', '_b_', 'c_.txt']);
        expect(safePathSegments('')).toEqual([]);
        expect(safePathSegments(Array(30).fill('x').join('/'))).toEqual([]);
    });

    it('names the folder being sent', () => {
        expect(folderName([{ path: 'Ảnh/a.jpg' }, { path: 'Ảnh/2024/b.jpg' }])).toBe('Ảnh');
        expect(folderName([{ path: 'A/a.jpg' }, { path: 'B/b.jpg' }])).toBe('Thư mục');
        expect(folderName([{ path: null, name: 'a.jpg' }])).toBeNull();
    });
});

describe('zip while receiving', () => {
    const bytesOf = (text) => new TextEncoder().encode(text);
    const contains = (haystack, needle) => {
        outer: for (let i = 0; i <= haystack.length - needle.length; i++) {
            for (let j = 0; j < needle.length; j++) {
                if (haystack[i + j] !== needle[j]) {
                    continue outer;
                }
            }

            return true;
        }

        return false;
    };

    it('packs files arriving one by one into a zip of the predicted size', async () => {
        const one = bytesOf('xin chào từ file một');
        const two = new Uint8Array(70000).map((_, i) => i % 251);
        const files = [
            { fid: 'a', name: 'mot.txt', path: 'Ảnh/mot.txt', size: one.length },
            { fid: 'b', name: 'hai.bin', path: 'Ảnh/con/hai.bin', size: two.length },
        ];
        let result = null;
        const zip = new ZipAssembler({ files, openTarget: async () => new MemorySink('Ảnh.zip'), onDone: (r) => { result = r; } });

        const first = zip.sinkFor('a');
        await first.write(one.slice().buffer);
        await first.close();

        const second = zip.sinkFor('b');
        await second.write(two.slice(0, 40000).buffer);
        await second.write(two.slice(40000).buffer);
        await second.close();

        await zip.done;
        const bytes = new Uint8Array(await result.file.arrayBuffer());

        expect(bytes.length).toBe(zip.length);
        expect([...bytes.slice(0, 4)]).toEqual([0x50, 0x4b, 0x03, 0x04]);
        expect(contains(bytes, bytesOf('Ảnh/con/hai.bin'))).toBe(true);
        expect(contains(bytes, one)).toBe(true);
        expect(contains(bytes, two)).toBe(true);
    });

    it('reports an error when a file of the folder is cancelled', async () => {
        let error = null;
        const zip = new ZipAssembler({
            files: [{ fid: 'a', name: 'a.txt', path: 'X/a.txt', size: 3 }, { fid: 'b', name: 'b.txt', path: 'X/b.txt', size: 3 }],
            openTarget: async () => new MemorySink('X.zip'),
            onError: (e) => { error = e; },
        });

        const first = zip.sinkFor('a');
        await first.write(bytesOf('abc').buffer);
        await first.close();
        zip.skip('b');
        await zip.done;

        expect(error?.message).toContain('không đầy đủ');
    });
});
