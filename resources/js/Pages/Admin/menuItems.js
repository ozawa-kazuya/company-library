export function getAdminMenuSections(overdueCount = 0) {
    return [
        {
            id: 'books',
            title: '資料管理',
            items: [
                {
                    id: 'books-create',
                    title: '資料登録',
                    href: route('admin.books.create'),
                    enabled: true,
                },
                {
                    id: 'books-edit',
                    title: '全資料編集',
                    href: route('admin.books.edit'),
                    enabled: true,
                },
                {
                    id: 'books-excel',
                    title: 'Excel入出力',
                    href: route('admin.books.import'),
                    enabled: true,
                },
                {
                    id: 'search-books',
                    title: '資料検索',
                    href: route('admin.search.books'),
                    enabled: true,
                },
            ],
        },
        {
            id: 'users',
            title: '利用者管理',
            items: [
                {
                    id: 'users-create',
                    title: '利用者登録',
                    href: route('admin.users.create'),
                    enabled: true,
                },
                {
                    id: 'search-users',
                    title: '利用者検索',
                    href: route('admin.search.users'),
                    enabled: true,
                },
                {
                    id: 'users-discard',
                    title: '利用者除名・パスワード再発行',
                    href: route('admin.users.discard'),
                    enabled: true,
                },
            ],
        },
        {
            id: 'loans',
            title: '貸出管理',
            items: [
                {
                    id: 'overdue',
                    title: '返却期限切れ',
                    href: route('admin.overdue'),
                    enabled: true,
                    badge: overdueCount,
                },
            ],
        },
    ];
}

/** @deprecated MenuTile 互換用。新UIでは getAdminMenuSections を使用 */
export function getAdminMenuItems(overdueCount = 0) {
    return getAdminMenuSections(overdueCount).flatMap((section) => section.items);
}
