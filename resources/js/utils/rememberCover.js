import { normalizeIsbn } from '@/utils/bookCover';

const remembered = new Set();

function storageKey(isbn, url) {
    return `coverRemember:${isbn}:${url}`;
}

export function rememberDisplayedCover(isbn, url) {
    const cleanIsbn = normalizeIsbn(isbn);
    const source = String(url ?? '').trim();

    if (!/^\d{13}$/.test(cleanIsbn) || !source || source.startsWith('/covers/')) {
        return;
    }

    const key = storageKey(cleanIsbn, source);

    if (remembered.has(key)) {
        return;
    }

    try {
        if (sessionStorage.getItem(key)) {
            remembered.add(key);
            return;
        }
    } catch {
        // sessionStorage が使えない場合はメモリだけ
    }

    remembered.add(key);

    if (!window.axios || typeof route !== 'function') {
        return;
    }

    window.axios
        .post(route('books.covers.remember'), {
            isbn: cleanIsbn,
            url: source,
        })
        .then(() => {
            try {
                sessionStorage.setItem(key, '1');
            } catch {
                // ignore
            }
        })
        .catch(() => {
            remembered.delete(key);
        });
}
