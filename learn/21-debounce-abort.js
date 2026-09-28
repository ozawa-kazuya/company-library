// 上級 第2課用: デバウンスと「古い処理のキャンセル」の縮小版
// 実行: node learn/21-debounce-abort.js

function debounceLookup(delayMs) {
    let timer = null;
    let requestId = 0;

    return function lookup(isbn) {
        clearTimeout(timer);
        const myId = ++requestId;

        timer = setTimeout(() => {
            // タイマー発火時に、より新しい入力が始まっていたら無視する
            if (myId !== requestId) {
                console.log("キャンセル:", isbn);
                return;
            }
            console.log("検索する:", isbn);
        }, delayMs);
    };
}

const lookup = debounceLookup(100);

lookup("9784798");
lookup("97847981");
lookup("9784798168494");

setTimeout(() => {
    console.log("→ 最後の ISBN だけ検索されれば成功");
}, 250);
