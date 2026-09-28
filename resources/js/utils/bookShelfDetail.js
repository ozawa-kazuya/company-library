import { formatStockLabel } from '@/utils/bookGrouping';

export function getBookStockSummary(groupedBook) {
    const available = groupedBook?.availableCopies ?? 0;
    const total = groupedBook?.totalCopies ?? 0;
    const stockLabel = formatStockLabel(available, total);

    return {
        available,
        total,
        stockLabel,
        hasAvailable: available > 0,
        displayStock:
            total <= 1
                ? available > 0
                    ? '在庫あり'
                    : '全冊貸出中'
                : `在庫 ${available}/${total} 冊`,
    };
}

export function getBookKey(book) {
    return book?.isbn || `id-${book?.id}`;
}

export function isAlreadyBorrowedByUser(isbn, borrowedIsbns = []) {
    if (!isbn) {
        return false;
    }

    return borrowedIsbns.includes(isbn);
}

export function collectBorrowedIsbns(myLoans = []) {
    return [...new Set(myLoans.map((loan) => loan?.book?.isbn).filter(Boolean))];
}
