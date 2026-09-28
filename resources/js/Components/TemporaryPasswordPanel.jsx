import { useState } from 'react';

async function copyText(value) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
        return;
    }

    const input = document.createElement('textarea');
    input.value = value;
    input.setAttribute('readonly', '');
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
}

function CopyButton({ value, label = 'コピー' }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        try {
            await copyText(value);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    return (
        <button
            type="button"
            onClick={handleCopy}
            className="shrink-0 font-black text-xs px-4 py-2 bg-[#0069e5] border-[#0051b3] border-b-4 text-white hover:bg-[#005bcc] rounded-xl transition-all shadow-sm active:scale-[0.95] active:border-b-0 active:mt-1"
        >
            {copied ? 'コピーしました' : label}
        </button>
    );
}

function credentialLine(item) {
    return `${item.user_code}\t${item.name}\t${item.password}`;
}

export default function TemporaryPasswordPanel({
    credentials = [],
    title,
    note,
}) {
    const items = Array.isArray(credentials) ? credentials.filter((item) => item?.password) : [];

    if (items.length === 0) {
        return null;
    }

    const allText = items.map(credentialLine).join('\n');

    return (
        <div
            role="status"
            className="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 sm:p-5 space-y-3 shadow-sm"
        >
            <p className="font-black text-sm text-emerald-800">{title}</p>
            <ul className="space-y-2">
                {items.map((item) => (
                    <li
                        key={`${item.user_code}-${item.password}`}
                        className="bg-white border border-emerald-100 rounded-xl px-3 py-3 flex flex-col sm:flex-row sm:items-center gap-2"
                    >
                        <div className="flex-1 min-w-0">
                            <p className="font-black text-sm text-gray-900">
                                {item.name}
                                <span className="ml-2 font-mono text-xs text-gray-400">{item.user_code}</span>
                            </p>
                            <p className="font-mono font-black text-lg text-gray-900 break-all mt-1">
                                {item.password}
                            </p>
                        </div>
                        <CopyButton value={item.password} label="パスワードをコピー" />
                    </li>
                ))}
            </ul>
            {items.length > 1 && (
                <div className="flex justify-end">
                    <CopyButton value={allText} label="全員分をコピー" />
                </div>
            )}
            {note && (
                <p className="text-[11px] font-bold text-emerald-800/80 leading-relaxed">{note}</p>
            )}
        </div>
    );
}
