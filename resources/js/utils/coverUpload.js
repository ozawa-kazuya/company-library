export function compressCoverFile(file, { maxWidth = 480, quality = 0.82 } = {}) {
    return new Promise((resolve, reject) => {
        if (!file || !file.type?.startsWith('image/')) {
            reject(new Error('画像ファイルを選んでください。'));
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            const scale = Math.min(1, maxWidth / Math.max(image.width, 1));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.width * scale));
            canvas.height = Math.max(1, Math.round(image.height * scale));
            const context = canvas.getContext('2d');

            if (!context) {
                reject(new Error('画像の変換に失敗しました。'));
                return;
            }

            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        reject(new Error('画像の変換に失敗しました。'));
                        return;
                    }
                    resolve(blob);
                },
                'image/jpeg',
                quality,
            );
        };

        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('画像を読み込めませんでした。'));
        };

        image.src = objectUrl;
    });
}

export async function uploadCoverImage(file) {
    const blob = await compressCoverFile(file);
    const formData = new FormData();
    formData.append('cover', blob, 'cover.jpg');

    const { data } = await window.axios.post(route('admin.books.covers.store'), formData);

    if (!data?.url) {
        throw new Error('表紙のアップロードに失敗しました。');
    }

    return data.url;
}

export async function persistCoverUrl(url) {
    const source = String(url ?? '').trim();

    if (!source || source.startsWith('/covers/')) {
        return source;
    }

    if (!/^https?:\/\//i.test(source)) {
        return source;
    }

    if (!window.axios || typeof route !== 'function') {
        return source;
    }

    try {
        const { data } = await window.axios.post(route('admin.books.covers.from-url'), {
            url: source,
        });

        return String(data?.url ?? source).trim() || source;
    } catch {
        return source;
    }
}
