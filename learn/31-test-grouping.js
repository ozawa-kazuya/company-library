import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    groupBooksByIsbn,
    formatStockLabel,
} from '../resources/js/utils/bookGrouping.js';

test('同じISBNの2冊のうち1冊が貸出中なら在庫は1', () => {
    const grouped = groupBooksByIsbn([
        { id: 1, isbn: '9784-1', title: 'Laravel入門', loans: [] },
        { id: 2, isbn: '9784-1', title: 'Laravel入門', loans: [{ id: 10 }] },
    ]);

    assert.equal(grouped.length, 1);
    assert.equal(grouped[0].totalCopies, 2);
    assert.equal(grouped[0].availableCopies, 1);
    assert.equal(grouped[0].borrowedCopies, 1);
});

test('1冊で空きがあれば在庫ラベルは出さない', () => {
    assert.equal(formatStockLabel(1, 1), null);
});

test('1冊で貸出中なら貸出中と出す', () => {
    assert.equal(formatStockLabel(0, 1), '貸出中');
});
