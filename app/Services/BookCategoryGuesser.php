<?php

namespace App\Services;

use App\Support\BookCategories;

class BookCategoryGuesser
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private const RULES = [
        ['C#・.NET', '/c#|csharp|\.net|asp\.net|vb\.net|visual\s*basic|winforms|wpf/u'],
        ['Excel・VBA', '/excel|vba|エクセル|スプレッドシート|ピボット/u'],
        ['IT資格・試験対策', '/資格|試験|パスポート|基本情報|応用情報|簿記|検定|it\s*パス|fe\s|ap\s|sc\s|nw\s|db\s|sg\s|pm\s|sa\s/u'],
        ['PHP・Laravel', '/php|laravel|symfony|wordpress|cakephp|codeigniter/u'],
        ['Web技術・セキュリティ', '/セキュリティ|security|oauth|jwt|xss|csrf|https|rest\s*api|graphql|websocket/u'],
        ['インフラ・クラウド', '/aws|azure|gcp|docker|kubernetes|k8s|linux|サーバ|インフラ|cloud|terraform|ansible|nginx|apache/u'],
        ['データベース', '/sql|mysql|postgresql|oracle|database|mongodb|redis|sqlite|db\s|データベース/u'],
        ['テスト・品質', '/テスト|tdd|bdd|jest|cypress|selenium|品質|qa|自動テスト|単体テスト/u'],
        ['開発ツール・バージョン管理', '/git|github|gitlab|svn|ci\/cd|jenkins|vscode|vim|devtools|バージョン管理/u'],
        ['フロントエンド・Web制作', '/フロント|react|vue|angular|next\.js|nuxt|html|css|ui|ux|figma|配色|レイアウト|デザイン|web\s*制作|tailwind/u'],
        ['ソフトウェア設計・開発', '/設計|uml|アーキテクチャ|ddd|クリーン|リファクタ|パターン|agile|スクラム|オブジェクト指向/u'],
        ['ビジネス・一般', '/ビジネス|マナー|プロジェクト|リーダー|経営|営業|英語|duo|コミュニケーション|一般/u'],
        ['ソフトウェア開発', '/python|java|javascript|typescript|go\s|rust|kotlin|swift|プログラミング|開発入門|アルゴリズム|コード/u'],
    ];

    public function guess(string $title): string
    {
        $normalizedTitle = mb_strtolower($title);

        foreach (self::RULES as [$category, $pattern]) {
            if (preg_match($pattern, $normalizedTitle)) {
                return $category;
            }
        }

        return BookCategories::default();
    }
}
