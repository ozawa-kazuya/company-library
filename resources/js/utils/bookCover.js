export function normalizeIsbn(value) {
    return String(value ?? '').replace(/[-\s_＿]/g, '').trim();
}

/** Google Books の ISBN 直リンクは「image not available」固定画像を返す */
export function isUnavailableCoverUrl(url) {
    const value = String(url ?? '').trim();

    if (!value) {
        return true;
    }

    return value.includes('books.google.com/books/content?id=ISBN:');
}

/**
 * 表示に使う表紙 URL の候補。先頭から試し、失敗したら次へ進める。
 * OpenBD → Open Library → 版元ドットコム。
 */
export function coverCandidateUrls(isbn, cover = '') {
    const urls = [];
    const seen = new Set();

    const push = (value) => {
        const url = String(value ?? '').trim();

        if (!url || isUnavailableCoverUrl(url) || seen.has(url)) {
            return;
        }

        seen.add(url);
        urls.push(url);
    };

    push(cover);

    const cleanIsbn = normalizeIsbn(isbn);

    if (/^\d{13}$/.test(cleanIsbn)) {
        push(`https://cover.openbd.jp/${cleanIsbn}.jpg`);
        push(`https://covers.openlibrary.org/b/isbn/${cleanIsbn}-L.jpg?default=false`);
        push(`https://img.hanmoto.com/bd/img/${cleanIsbn}.jpg`);
    }

    return urls;
}

/**
 * 表紙 URL を組み立てる。保存済み cover を優先し、無ければ外部候補の先頭。
 */
export function buildCoverUrl(isbn, cover = '') {
    return coverCandidateUrls(isbn, cover)[0] ?? null;
}

/**
 * 実表紙ではない固定画。
 * - 版元ドットコムの 404: 約 50×71 の灰色 JPEG
 * - Google Books の「画像なし」: ちょうど 128×170
 */
export function isLikelyPlaceholderImage(img) {
    if (!img) {
        return false;
    }

    const width = img.naturalWidth;
    const height = img.naturalHeight;

    if (width < 80 || height < 100) {
        return true;
    }

    return width === 128 && height === 170;
}
