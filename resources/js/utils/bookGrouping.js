/**
 * 同一ISBNの書籍をグループ化して在庫情報を付与する
 */
export function groupBooksByIsbn(books = []) {
    const groups = new Map();

    for (const book of books) {
        const key = book.isbn || `id-${book.id}`;
        if (!groups.has(key)) {
            groups.set(key, {
                ...book,
                copies: [],
                totalCopies: 0,
                availableCopies: 0,
                borrowedCopies: 0,
            });
        }

        const group = groups.get(key);
        const isBorrowed =
            (book.loans && book.loans.length > 0) || book.status === 'rented';

        group.copies.push(book);
        group.totalCopies += 1;
        if (isBorrowed) {
            group.borrowedCopies += 1;
        } else {
            group.availableCopies += 1;
        }
    }

    return Array.from(groups.values());
}

export function formatCopyLabel(copyNumber, totalCopies) {
    if (totalCopies <= 1) {
        return '';
    }

    return `第${copyNumber}冊`;
}

export function formatStockLabel(availableCopies, totalCopies) {
    if (totalCopies <= 1) {
        return availableCopies > 0 ? null : '貸出中';
    }

    return `在庫 ${availableCopies}/${totalCopies} 冊`;
}
