import React from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TemporaryPasswordPanel from '@/Components/TemporaryPasswordPanel';

export default function UserCreate() {
    const flash = usePage().props.flash ?? {};
    const credentials = Array.isArray(flash.created_credentials) ? flash.created_credentials : [];

    const { data, setData, post, processing, errors, reset } = useForm({
        users: [
            { emp_id: '', name: '', email: '' },
            { emp_id: '', name: '', email: '' },
            { emp_id: '', name: '', email: '' },
            { emp_id: '', name: '', email: '' },
            { emp_id: '', name: '', email: '' },
        ]
    });

    const handleInputChange = (index, field, value) => {
        const updatedUsers = [...data.users];
        if (field === 'emp_id') {
            updatedUsers[index][field] = value.replace(/\D/g, '').slice(0, 3);
        } else {
            updatedUsers[index][field] = value;
        }
        setData('users', updatedUsers);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.users.store'), {
            onSuccess: () => reset()
        });
    };

    return (
        <AdminLayout title="5人同時・新規利用者登録" maxWidth="max-w-4xl">
            <div className="space-y-6">
                
                <div className="flex justify-between items-center border-b border-gray-300 pb-3">
                    <div className="flex items-center space-x-2">
                        <span className="text-2xl">👥</span>
                        <h2 className="font-black text-2xl text-gray-800 tracking-wide">新規利用者一括登録 (最大5名)</h2>
                    </div>
                </div>

                {credentials.length > 0 && (
                    <TemporaryPasswordPanel
                        credentials={credentials}
                        title={`${credentials.length} 名の登録が完了しました。人ごとに違う仮パスワードです。この画面を閉じると再表示できません。`}
                        note="本人へ仮パスワードを伝え、初回ログイン後に変更するよう案内してください。変更するまで本棚は使えません。"
                    />
                )}

                {errors.users && (
                    <div className="bg-red-500 text-white p-4 rounded-xl font-black text-xs md:text-sm shadow animate-fadeIn leading-relaxed">
                        ⚠️ {errors.users}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    
                    <div className="hidden sm:grid grid-cols-12 gap-3 px-4 text-xs font-black text-gray-400 uppercase tracking-wider">
                        <div className="col-span-1 text-center">NO</div>
                        <div className="col-span-2">社員番号</div>
                        <div className="col-span-4">社員名（フルネーム）</div>
                        <div className="col-span-5">メールアドレス</div>
                    </div>

                    <div className="space-y-2.5">
                        {data.users.map((user, index) => (
                            <div key={index} className="bg-white p-4 sm:p-3 rounded-2xl sm:rounded-xl border border-gray-200 shadow-sm grid grid-cols-12 gap-3 items-center hover:bg-gray-50/50 transition-all">
                                
                                <div className="col-span-12 sm:col-span-1 flex sm:justify-center">
                                    <span className="bg-gray-100 text-gray-500 font-mono font-black text-xs px-2.5 py-1 rounded-lg">
                                        #{index + 1}
                                    </span>
                                </div>

                                <div className="col-span-12 sm:col-span-2 space-y-1 sm:space-y-0">
                                    <label className="block sm:hidden text-[10px] font-black text-gray-400 uppercase">社員番号</label>
                                    <input
                                        type="text"
                                        value={user.emp_id}
                                        onChange={(e) => handleInputChange(index, 'emp_id', e.target.value)}
                                        placeholder={String(index + 1).padStart(3, '0')}
                                        className="w-full bg-gray-50 border border-gray-300 rounded-xl sm:rounded-lg px-3.5 py-2.5 sm:py-2 text-sm text-gray-800 focus:border-gray-500 focus:ring-4 focus:ring-gray-100 focus:outline-none font-mono font-bold"
                                    />
                                </div>

                                <div className="col-span-12 sm:col-span-4 space-y-1 sm:space-y-0">
                                    <label className="block sm:hidden text-[10px] font-black text-gray-400 uppercase">社員名</label>
                                    <input
                                        type="text"
                                        value={user.name}
                                        onChange={(e) => handleInputChange(index, 'name', e.target.value)}
                                        placeholder="田中 太郎"
                                        className="w-full bg-gray-50 border border-gray-300 rounded-xl sm:rounded-lg px-3.5 py-2.5 sm:py-2 text-sm text-gray-800 focus:border-gray-500 focus:ring-4 focus:ring-gray-100 focus:outline-none font-bold"
                                    />
                                </div>

                                <div className="col-span-12 sm:col-span-5 space-y-1 sm:space-y-0">
                                    <label className="block sm:hidden text-[10px] font-black text-gray-400 uppercase">メールアドレス</label>
                                    <input
                                        type="email"
                                        value={user.email}
                                        onChange={(e) => handleInputChange(index, 'email', e.target.value)}
                                        placeholder="taro.tanaka@example.co.jp"
                                        className="w-full bg-gray-50 border border-gray-300 rounded-xl sm:rounded-lg px-3.5 py-2.5 sm:py-2 text-sm text-gray-800 focus:border-gray-500 focus:ring-4 focus:ring-gray-100 focus:outline-none font-bold"
                                    />
                                </div>

                            </div>
                        ))}
                    </div>

                    <p className="text-[11px] text-gray-400 font-bold bg-white p-3.5 rounded-2xl border border-gray-200 leading-relaxed shadow-sm">
                        💡 5マス全てを埋める必要はありません。入力された行だけが自動判定されて一括保存されます。社員番号・氏名・メールアドレスはセットで入力してください。登録完了後、人ごとに違う仮パスワードが表示されます。
                    </p>

                    <div className="pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-[#03c04a] border-b-4 border-[#02963a] text-white font-black text-base py-4 rounded-xl shadow-md transition-all hover:bg-[#03ad43] active:scale-[0.99] active:border-b-0 active:mt-1 disabled:opacity-50"
                        >
                            👥 入力した社員を5人まとめて一括登録する
                        </button>
                    </div>

                </form>
            </div>
        </AdminLayout>
    );
}
