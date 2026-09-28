import { usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import AdminMenuButton from '@/Components/Admin/AdminMenuButton';
import { getAdminMenuSections } from './menuItems';

export default function Menu({ overdueCount = 0 }) {
    const { auth } = usePage().props;
    const sections = getAdminMenuSections(overdueCount);
    const userName = auth?.user?.name ?? '管理者';

    return (
        <AdminLayout title="管理ダッシュボード" showBackLink={false} maxWidth="max-w-3xl">
            <div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6 md:p-8 space-y-8">
                <div>
                    <h2 className="text-2xl font-black text-gray-800 tracking-wide">
                        ダッシュボード
                    </h2>
                    <p className="text-sm text-gray-600 font-bold mt-3">
                        {userName} さん、こんにちは。画面上部の{' '}
                        <span className="text-[#ff9830]">集計</span> で蔵書・貸出の状況を切り替えられます。
                    </p>
                </div>

                {sections.map((section) => (
                    <section key={section.id} aria-labelledby={`section-${section.id}`}>
                        <h3
                            id={`section-${section.id}`}
                            className="text-sm font-black text-gray-500 uppercase tracking-wider mb-3 border-b border-gray-200 pb-2"
                        >
                            {section.title}
                        </h3>
                        <div className="space-y-3">
                            {section.items.map((item) => (
                                <AdminMenuButton key={item.id} {...item} />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </AdminLayout>
    );
}
