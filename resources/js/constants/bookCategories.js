export const BOOK_CATEGORIES = [
    'C#・.NET',
    'Excel・VBA',
    'IT資格・試験対策',
    'PHP・Laravel',
    'Web技術・セキュリティ',
    'インフラ・クラウド',
    'ソフトウェア開発',
    'ソフトウェア設計・開発',
    'データベース',
    'テスト・品質',
    'ビジネス・一般',
    'フロントエンド・Web制作',
    '開発ツール・バージョン管理',
];

export const DEFAULT_BOOK_CATEGORY = 'ソフトウェア開発';

export const BOOK_CATEGORY_FILTER_OPTIONS = ['すべて', ...BOOK_CATEGORIES];

const CATEGORY_RULES = [
    ['C#・.NET', /c#|csharp|\.net|asp\.net|vb\.net|visual\s*basic|winforms|wpf/],
    ['Excel・VBA', /excel|vba|エクセル|スプレッドシート|ピボット/],
    ['IT資格・試験対策', /資格|試験|パスポート|基本情報|応用情報|簿記|検定|it\s*パス|fe\s|ap\s|sc\s|nw\s|db\s|sg\s|pm\s|sa\s/],
    ['PHP・Laravel', /php|laravel|symfony|wordpress|cakephp|codeigniter/],
    ['Web技術・セキュリティ', /セキュリティ|security|oauth|jwt|xss|csrf|https|rest\s*api|graphql|websocket/],
    ['インフラ・クラウド', /aws|azure|gcp|docker|kubernetes|k8s|linux|サーバ|インフラ|cloud|terraform|ansible|nginx|apache/],
    ['データベース', /sql|mysql|postgresql|oracle|database|mongodb|redis|sqlite|db\s|データベース/],
    ['テスト・品質', /テスト|tdd|bdd|jest|cypress|selenium|品質|qa|自動テスト|単体テスト/],
    ['開発ツール・バージョン管理', /git|github|gitlab|svn|ci\/cd|jenkins|vscode|vim|devtools|バージョン管理/],
    ['フロントエンド・Web制作', /フロント|react|vue|angular|next\.js|nuxt|html|css|ui|ux|figma|配色|レイアウト|デザイン|web\s*制作|tailwind/],
    ['ソフトウェア設計・開発', /設計|uml|アーキテクチャ|ddd|クリーン|リファクタ|パターン|agile|スクラム|オブジェクト指向/],
    ['ビジネス・一般', /ビジネス|マナー|プロジェクト|リーダー|経営|営業|英語|duo|コミュニケーション|一般/],
    ['ソフトウェア開発', /python|java|javascript|typescript|go\s|rust|kotlin|swift|プログラミング|開発入門|アルゴリズム|コード/],
];

export function guessBookCategory(title) {
    const normalized = String(title ?? '').toLowerCase();

    if (normalized === '') {
        return DEFAULT_BOOK_CATEGORY;
    }

    for (const [category, pattern] of CATEGORY_RULES) {
        if (pattern.test(normalized)) {
            return category;
        }
    }

    return DEFAULT_BOOK_CATEGORY;
}
