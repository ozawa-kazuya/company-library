# 社内図書貸出管理システム SQLクエリ・ORM 使用方針

| 項目 | 内容 |
|------|------|
| 対象 | 開発担当 |
| 最終更新 | 2026年9月 |

**業務の読み書きは Eloquent（Laravel ORM）に統一する。** アプリコードに生 SQL 文字列を書かない。画面（React）から DB には直接つながない。

モデルは `app/Models/`（`User` / `Book` / `Loan` / `CoverImage`）。テーブル定義は [スキーマ設計とマイグレーション](./スキーマ設計とマイグレーション.md)。SQL 相当の説明は [詳細設計書 第8章](./詳細設計書.md)。

---

## 1. 方針（要約）

| やり方 | 使うか | 置き場 |
|--------|--------|--------|
| Eloquent（`Book::where`、`Loan::query()`、リレーション） | **使う（本線）** | `app/` |
| ローカルスコープ（例: `Loan::overdue()`） | 使う | モデル |
| `DB::transaction` | 複数テーブルをまとめて書くとき | サービス（例: 蔵書 Excel 上書き） |
| Query Builder（`DB::table`） | アプリでは使わない。マイグレーションの一括更新のみ実績あり | `database/migrations/` |
| `DB::statement` / 生 SQL | スキーマが Eloquent で表現できないときだけ | マイグレーション（MySQL の `MEDIUMBLOB`） |
| ストアドプロシージャ・ビュー・トリガー | **使わない** | — |
| 別 ORM（Doctrine、Prisma 等） | **使わない** | — |

利用者入力は `where('isbn', $isbn)` のように **プレースホルダ経由** にする。文字列連結で SQL を組み立てない（SQL インジェクション対策。Eloquent のバインドがこれを担う）。全体の対策は [セキュリティ対策](./セキュリティ対策.md)。

---

## 2. Eloquent の使い方

| 目的 | 例 |
|------|-----|
| 1 件 | `Book::findOrFail($id)` |
| 条件 | `Loan::where('user_id', $id)->whereNull('returned_at')` |
| 関連の有無 | `whereDoesntHave('currentLoan')` / `whereHas('book', …)` |
| N+1 防止 | `Book::with(['currentLoan.user'])` |
| 期限切れ | `Loan::query()->overdue()`（`scopeOverdue`） |
| 貸出中の正 | `loans.returned_at IS NULL`。リレーション `Book::currentLoan()` |

冊の採番・空き冊の取得はモデルの静的メソッドに寄せる（`Book::nextCopyNumber`、`findAvailableCopyByIsbn`）。コントローラに同じ条件を散らさない。

一括代入は `fillable` を正とする（`Book` / `Loan` / `CoverImage`）。`User` は現状 `$guarded = []`。新規モデルでは `fillable` を付ける。

パスワード・表紙バイナリは JSON に出さない（`hidden`）。

---

## 3. 生 SQL を書いてよい範囲

| 許可 | 内容 |
|------|------|
| マイグレーション | ドライバ差（MySQL の `MEDIUMBLOB` など） |
| 障害調査 | `php artisan tinker` または `mysql` クライアント。本番は [バックアップ](./データバックアップリストア.md) 後 |
| ドキュメント | [詳細設計書](./詳細設計書.md) の「SQL 相当」（実装は Eloquent） |

コントローラやサービスに `DB::select('SELECT … '.$userInput)` は書かない。`whereRaw` も、バインド無しの埋め込みは禁止。

---

## 4. トランザクション

単一の INSERT / UPDATE（貸出・返却）は、現状明示の `DB::transaction` は無い。複数テーブルを同時に消す処理（Excel 取り込みで冊を削除するとき）はトランザクションで囲む。

同時貸出の競合はアプリ層（空き冊の再判定）で抑えている。行ロック（`lockForUpdate`）は未使用。必要になったらトランザクション内に追加する。

---

## 5. フロント・外部

React は Inertia でサーバーが渡したデータを表示するだけである。ブラウザから MySQL へクエリは出さない。

外部の書誌 API（OpenBD 等）は HTTP であり、社内 DB の SQL ではない（[外部サービスAPI](./外部サービスAPI.md)）。

---

## 6. 関連資料

| 資料 | 用途 |
|------|------|
| [詳細設計書 第8章](./詳細設計書.md) | 期限切れ・採番などの SQL 相当 |
| [スキーマ設計とマイグレーション](./スキーマ設計とマイグレーション.md) | テーブルと migrate |
| [技術スタック一覧](./技術スタック一覧.md) | Eloquent の位置づけ |
| [基本設計書 10-3](./基本設計書.md) | SQL インジェクション対策 |
| [セキュリティ対策](./セキュリティ対策.md) | CORS / XSS / CSRF も含む |
| [AWSセットアップ](./AWSセットアップ.md) | ホスティング先 |
| [環境変数とシークレット](./環境変数とシークレット.md) | `.env` |

---

## 改訂履歴

| 日付 | 内容 |
|------|------|
| 2026-09-28 | 学習用 BigQuery の案内を削除 |
| 2026-09-01 | 初版 |
