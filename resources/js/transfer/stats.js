/**
 * Loại kết nối đang dùng, đọc từ RTCPeerConnection.getStats():
 *  - 'lan':    trực tiếp trong cùng mạng (cả hai địa chỉ nội bộ)
 *  - 'direct': trực tiếp qua Internet
 *  - 'relay':  đi vòng qua máy chủ trung chuyển (TURN)
 * Trả null khi chưa chọn được cặp địa chỉ.
 */
export function connectionKind(reports) {
    const byId = new Map();
    let pairId = null;

    for (const report of reports) {
        byId.set(report.id, report);
        if (report.type === 'transport' && report.selectedCandidatePairId) {
            pairId = report.selectedCandidatePairId;
        }
    }

    let pair = pairId ? byId.get(pairId) : null;

    if (! pair) {
        pair = [...byId.values()].find((report) => report.type === 'candidate-pair' && (report.selected || report.nominated) && report.state === 'succeeded') ?? null;
    }
    if (! pair) {
        return null;
    }

    const local = byId.get(pair.localCandidateId);
    const remote = byId.get(pair.remoteCandidateId);

    if (! local || ! remote) {
        return null;
    }

    const types = [local.candidateType, remote.candidateType];

    if (types.includes('relay')) {
        return 'relay';
    }

    return types.every((type) => type === 'host') ? 'lan' : 'direct';
}

export const CONNECTION_LABELS = {
    lan: 'Trực tiếp · cùng mạng',
    direct: 'Trực tiếp qua Internet',
    relay: 'Qua máy chủ trung chuyển',
};
