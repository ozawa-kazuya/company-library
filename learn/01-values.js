// 文法 第1課: 値と変数
// 実行: ターミナルで  node learn/01-values.js

// ----- 値（データそのもの） -----
// 文字は " または ' で囲む（文字列）
console.log("こんにちは");
console.log("図書管理システム");

// 数字はそのまま書く（数値）
console.log(3);
console.log(1 + 2);

// はい / いいえ（真偽値）
console.log(true);
console.log(false);

// 該当なし
console.log(null);

// ----- 変数（名前を付けて覚えておく） -----
const appName = "図書管理システム";
const userCode = "001";
const maxDigits = 3;
const isLoggedIn = false;

console.log(appName);
console.log(userCode);
console.log(maxDigits);
console.log(isLoggedIn);

// const = あとから入れ直さない
// let   = あとから入れ直してよい

let searchQuery = "";
console.log(searchQuery);

searchQuery = "React";
console.log(searchQuery);

// ----- 練習（下の ??? を自分で書き換えて、もう一度 node で実行） -----
// 社員番号を表す変数 employeeCode を作り、自分の好きな3桁を入れて
// console.log で表示してください。

const employeeCode = "???";
console.log("社員番号:", employeeCode);
