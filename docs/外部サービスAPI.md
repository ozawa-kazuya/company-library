# 社内図書貸出管理システム 外部サービス・API

| 項目 | 内容 |
|------|------|
| 対象 | 開発担当・運用担当 |
| 最終更新 | 2026年9月 |
| 対象環境 | 本サーバー（アプリが外向き HTTPS で呼ぶ先） |

公開 API として外に出すものはありません（[API仕様](./API仕様.md)）。この資料は **こちらが依存する外部** です。


---

## 1. 必須かどうか

貸出・返却・ログインは **自前の MySQL だけ** で動きます。外部が落ちても、すでに登録済みの本は借り返せます。

| 区分 | 止まると困ること | 止まってもできること |
|------|------------------|----------------------|
| 書誌・表紙 API | 新規の ISBN 自動取得、表紙の自動取得 | 手入力登録、既存の貸出・返却 |
| Slack | 期限切れの自動通知 | 管理画面の期限切れ一覧 |
| メール | パスワード再設定メール | 管理者がパスワード再発行 |

本サーバー（Fargate）からインターネットへ出られること（書誌・Slack・メール）が前提です。外向き HTTPS を閉じると、上記の自動取得と通知だけが失敗します。

---

## 2. 一覧

| サービス | 必須 | 用途 | 認証 | 呼び出し元 |
|----------|------|------|------|-----------|
| OpenBD | 任意 | ISBN の書誌・表紙（最優先） | なし | サーバー `BookIsbnLookup` |
| 版元ドットコム | 任意 | 書影 | なし | サーバー `BookCoverResolver` |
| Open Library | 任意 | 書影 | なし | サーバー / ブラウザ（表紙 URL） |
| Google Books | 任意 | 書誌・表紙、画面の書籍検索 | キー任意 | サーバー / ブラウザ |
| 国立国会図書館（NDL） | 任意 | 題名から ISBN | なし | サーバー `NdlBookLookup` |
| Slack Incoming Webhook | 任意 | 期限切れの管理チャンネル | Webhook URL | バッチ |
| Slack Web API | 任意 | 一般利用者への DM | Bot Token | バッチ |
| メール（SMTP 等） | 任意 | パスワード再設定 | `MAIL_*` | Laravel Mail |

AWS の **SES は使わない。** ホスティングは本サーバー（Fargate + RDS）が AWS。メールは社内または契約 SMTP。

---

## 3. 書誌・表紙（登録時）

資料登録・全資料編集の再取得・Excel 取り込みで、サーバーが順に試します。どれかが失敗しても次へ進み、最後は手入力です。

```
ISBN あり
  → OpenBD（書誌 JSON）
  → 表紙: cover.openbd.jp → 版元ドットコム → Open Library
  → 足りなければ Google Books（ISBN）

題名だけ / 表紙なし
  → Google Books（題名）
  → NDL OpenSearch（題名 → ISBN）→ 再度 ISBN 検索
```

| 先 | メソッド | URL | タイムアウト |
|----|----------|-----|--------------|
| OpenBD | GET | `https://api.openbd.jp/v1/get?isbn={ISBN13}` | 5 秒 |
| OpenBD 表紙 | GET / HEAD | `https://cover.openbd.jp/{ISBN13}.jpg` | 3〜5 秒 |
| 版元ドットコム | GET / HEAD | `https://img.hanmoto.com/bd/img/{ISBN13}.jpg` | 3〜5 秒 |
| Open Library | GET / HEAD | `https://covers.openlibrary.org/b/isbn/{ISBN13}-L.jpg?default=false` | 3〜5 秒 |
| Google Books | GET | `https://www.googleapis.com/books/v1/volumes?q=isbn:{ISBN13}` または題名 | 5 秒 |
| NDL | GET | `https://iss.ndl.go.jp/api/opensearch?title=…&books=true&cnt=10` | 15 秒 |

Google Books は `.env` の `GOOGLE_BOOKS_API_KEY` があれば `key=` を付けます。空なら無認証枠です。クォータに当たると表紙・書誌の補完が減ります。

コード:

- `app/Services/BookIsbnLookup.php`
- `app/Services/BookCoverResolver.php`
- `app/Services/GoogleBooksLookup.php`
- `app/Services/NdlBookLookup.php`
- `app/Services/CoverLookup.php`

表紙を自前保存するとき（`POST /covers/remember`）、サーバーが表紙 URL を GET します（8 秒）。許可ホストは `CoverSourceGuard`（openbd / hanmoto / openlibrary / Google 系）です。

---

## 4. ブラウザから直接呼ぶもの

ログイン後の画面が、サーバーを経由せず外部へ出ます。

| 画面 | 先 | URL |
|------|----|-----|
| 書籍検索 | Google Books | `https://www.googleapis.com/books/v1/volumes?q=…&maxResults=9` |
| 本棚など | 表紙画像 | `books.cover` に入っている外部 URL（上記ホスト） |

書籍検索のフロントからは API キーを付けません。キーが必要な場合はサーバー側の lookup を使います。

表紙 URL が外部のままだと、社員のブラウザがそのホストへ画像取得します。自前保存後は `/covers/{uuid}` のみです。

---

## 5. Slack

期限切れバッチ（毎分）が使います。未設定なら送りません。貸出そのものは止まりません。

| 用途 | 設定 | API |
|------|------|-----|
| 管理チャンネル | `SLACK_OVERDUE_WEBHOOK_URL` | Incoming Webhook へ POST（JSON `text`） |
| メール → Slack ユーザー | `SLACK_BOT_TOKEN` | `users.lookupByEmail` |
| DM 開始 | 同上 | `conversations.open` |
| DM 送信 | 同上 | `chat.postMessage` |

タイムアウトは各 10 秒です。権限の目安: `users:read.email` / `chat:write` / `im:write`。

図書システムの `users.email` と Slack のメールが一致しない人、および管理者には DM しません。チャンネル投稿は行われます。

確認: `php artisan library:notify-overdue-slack --dry-run`（[保守運用手順書 第5章](./保守運用手順書.md)）。

---

## 6. メール

パスワード再設定（`/forgot-password`）だけが外部メールに依存します。

| 項目 | 内容 |
|------|------|
| 設定 | `.env` の `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_*` |
| 既定 | `MAIL_MAILER=log`（ファイルに書くだけ。本番では SMTP 等に変える） |
| 失敗時 | 管理者のパスワード再発行で代替 |

本サーバーでは AWS SES を使いません。メールは社内または契約 SMTP です。

---

## 7. 障害時の切り分け

| 症状 | 疑う先 |
|------|--------|
| ISBN を入れても題名が埋まらない | OpenBD → Google Books。手入力は可 |
| 表紙が出ない | 版元・Open Library・Google。手動アップロードまたは remember |
| 書籍検索画面が空 | ブラウザから Google Books へ出られるか |
| Slack が来ない | cron、Webhook、Bot Token、件数 0 |
| 再設定メールが来ない | `MAIL_*`、`laravel.log` |

外部 API の障害で HTTP 500 にしない実装です（タイムアウトは握りつぶして `null`）。

---

## 8. 依存しないもの（混同しやすい）

| もの | 理由 |
|------|------|
| 自前の公開 REST | 出していない |
| AWS | 本サーバーの箱。アプリが呼ぶ外部 API ではない（[AWSセットアップ](./AWSセットアップ.md)） |
| Packagist / npm | ビルド時のみ。実行時は `vendor` / `public/build` |
| MySQL | 自前（または社内）DB。外部 SaaS ではない |

---

## 改訂履歴

| 日付 | 内容 |
|------|------|
| 2026-09-01 | 初版 |
| 2026-09-02 | Grafanaダッシュボード資料への案内を追加 |
