import { useRef, useState } from 'react';
import { uploadCoverImage } from '@/utils/coverUpload';

export default function CoverImagePicker({ onUploaded, onError, disabled = false }) {
    const inputRef = useRef(null);
    const [busy, setBusy] = useState(false);

    const handleChange = async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        setBusy(true);

        try {
            const url = await uploadCoverImage(file);
            onUploaded(url);
        } catch (error) {
            const message =
                error?.response?.data?.message ||
                error?.response?.data?.errors?.cover?.[0] ||
                error?.message ||
                '表紙のアップロードに失敗しました。';
            onError?.(message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={handleChange}
            />
            <button
                type="button"
                disabled={disabled || busy}
                onClick={() => inputRef.current?.click()}
                className="px-3 py-1.5 text-[11px] font-black rounded-lg border border-[#00a0e9] text-[#007bbf] bg-sky-50 hover:bg-sky-100 disabled:opacity-50"
            >
                {busy ? 'アップロード中…' : '表紙画像を選ぶ'}
            </button>
        </>
    );
}
