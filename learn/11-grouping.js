// 中級 第2課用: ISBN グループ化の縮小版
// 実行: node learn/11-grouping.js

function groupBooksByIsbn(books) {
    const groups = new Map();

    for (const book of books) {
        const key = book.isbn || `id-${book.id}`;
        if (!groups.has(key)) {
            groups.set(key, {
                title: book.title,
                isbn: book.isbn,
                copies: [],
                totalCopies: 0,
                availableCopies: 0,
            });
        }

        const group = groups.get(key);
        const isBorrowed = book.loans && book.loans.length > 0;

        group.copies.push(book);
        group.totalCopies += 1;
        if (!isBorrowed) {
            group.availableCopies += 1;
        }
    }

    return Array.from(groups.values());
}

const books = [
    { id: 1, isbn: "9784-1", title: "Laravel入門", loans: [] },
    { id: 2, isbn: "9784-1", title: "Laravel入門", loans: [{ id: 10 }] },
    { id: 3, isbn: "9784-2", title: "React実践", loans: [] },
];

const grouped = groupBooksByIsbn(books);

console.log("グループ数:", grouped.length);
// 予測: 2（ISBNが2種類）

console.log(
    grouped.map((g) => ({
        title: g.title,
        total: g.totalCopies,
        available: g.availableCopies,
    })),
);
// 予測: Laravel入門 は 2冊中 1冊空き、React実践 は 1冊空き
