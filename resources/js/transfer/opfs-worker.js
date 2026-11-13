/**
 * Worker ghi file vào bộ nhớ riêng của trang (OPFS) bằng SyncAccessHandle: chạy được trên Safari
 * (iPhone) từ iOS 15.2, ghi thẳng xuống đĩa, không giữ dữ liệu trong RAM.
 */
let handle = null;
let fileName = null;
let offset = 0;

async function folder() {
    const root = await navigator.storage.getDirectory();

    return root.getDirectoryHandle('gui-file', { create: true });
}

self.onmessage = async ({ data }) => {
    try {
        if (data.op === 'open') {
            fileName = data.name;
            const fileHandle = await (await folder()).getFileHandle(fileName, { create: true });
            handle = await fileHandle.createSyncAccessHandle();
            handle.truncate(0);
            offset = 0;
        } else if (data.op === 'write') {
            const view = new Uint8Array(data.data);
            const written = handle.write(view, { at: offset });
            if (written !== view.byteLength) {
                throw new Error('Không ghi được, có thể máy đã hết bộ nhớ.');
            }
            offset += written;
        } else if (data.op === 'close') {
            handle.flush();
            handle.close();
            handle = null;
        } else if (data.op === 'abort') {
            handle?.close();
            handle = null;
            if (fileName) {
                await (await folder()).removeEntry(fileName).catch(() => {});
            }
        }
        self.postMessage({ seq: data.seq });
    } catch (error) {
        self.postMessage({ seq: data.seq, error: error?.name === 'QuotaExceededError' ? 'Máy đã hết bộ nhớ trống.' : String(error?.message ?? error) });
    }
};
