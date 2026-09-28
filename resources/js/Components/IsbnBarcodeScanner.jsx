import { useEffect, useRef, useState } from 'react';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

const SCANNER_ID = 'isbn-barcode-scanner';

function normalizeIsbn(value) {
    return value.replace(/[-\s_＿]/g, '').trim();
}

export default function IsbnBarcodeScanner({ onScan }) {
    const scannerRef = useRef(null);
    const onScanRef = useRef(onScan);
    const lastScanRef = useRef('');
    const [cameraError, setCameraError] = useState('');
    const [isReady, setIsReady] = useState(false);

    onScanRef.current = onScan;

    useEffect(() => {
        if (!window.isSecureContext) {
            setCameraError(
                'カメラは HTTPS 接続でのみ利用できます。現在は HTTP のため起動できません。手入力をご利用ください。HTTPS 化は AWS デプロイ手順書を参照してください。',
            );
            return undefined;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraError(
                'このブラウザはカメラに対応していません。手入力をお試しください。',
            );
            return undefined;
        }

        let isMounted = true;
        const html5QrCode = new Html5Qrcode(SCANNER_ID);
        scannerRef.current = html5QrCode;

        const config = {
            fps: 10,
            qrbox: { width: 280, height: 120 },
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.CODE_128,
            ],
        };

        html5QrCode
            .start(
                { facingMode: 'environment' },
                config,
                (decodedText) => {
                    const clean = normalizeIsbn(decodedText);
                    if (!/^\d{10,13}$/.test(clean) || clean === lastScanRef.current) {
                        return;
                    }
                    lastScanRef.current = clean;
                    onScanRef.current(clean);
                    setTimeout(() => {
                        lastScanRef.current = '';
                    }, 2000);
                },
                () => {},
            )
            .then(() => {
                if (isMounted) {
                    setIsReady(true);
                }
            })
            .catch((error) => {
                if (isMounted) {
                    const denied = error?.name === 'NotAllowedError' || error?.name === 'PermissionDeniedError';
                    setCameraError(
                        denied
                            ? 'カメラの使用が拒否されました。ブラウザのアドレスバー付近でカメラを「許可」するか、手入力をお試しください。'
                            : 'カメラを起動できませんでした。ブラウザのカメラ許可を確認するか、手入力をお試しください。',
                    );
                }
            });

        return () => {
            isMounted = false;
            const scanner = scannerRef.current;
            if (!scanner) {
                return;
            }
            const stopPromise = scanner.isScanning
                ? scanner.stop()
                : Promise.resolve();
            stopPromise
                .then(() => scanner.clear())
                .catch(() => {});
        };
    }, []);

    return (
        <div className="space-y-3">
            {cameraError ? (
                <p className="text-xs text-red-600 font-bold bg-red-50 border border-red-200 rounded-xl p-3">
                    {cameraError}
                </p>
            ) : (
                <>
                    <div
                        id={SCANNER_ID}
                        className="rounded-2xl overflow-hidden border-2 border-gray-300 bg-black min-h-[240px]"
                    />
                    <p className="text-xs text-gray-500 font-bold text-center">
                        {isReady
                            ? '本のISBNバーコードを枠内に合わせてください'
                            : 'カメラを起動しています...'}
                    </p>
                </>
            )}
        </div>
    );
}
