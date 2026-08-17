import React, { useState, useEffect } from 'react';
import QRCode from 'qrcode';

interface QRCodeProps {
    value: string;
    size?: number;
    className?: string;
    bgColor?: string;
    fgColor?: string;
}

export const QRCodeSVG: React.FC<QRCodeProps> = ({
    value,
    size = 180,
    className = '',
    bgColor = '#FFFFFF',
    fgColor = '#000000',
}) => {
    const [svgDataUrl, setSvgDataUrl] = useState<string>('');

    useEffect(() => {
        let isMounted = true;
        const generate = async () => {
            try {
                const url = await QRCode.toDataURL(value || 'https://brewos.local', {
                    width: size * 2,
                    margin: 2,
                    color: {
                        dark: fgColor,
                        light: bgColor,
                    },
                    errorCorrectionLevel: 'M',
                });
                if (isMounted) {
                    setSvgDataUrl(url);
                }
            } catch (err) {
                console.error('[QRCodeSVG] Failed to generate QR code:', err);
            }
        };

        generate();

        return () => {
            isMounted = false;
        };
    }, [value, size, bgColor, fgColor]);

    if (!svgDataUrl) {
        return (
            <div
                style={{ width: size, height: size }}
                className={`bg-stone-100 animate-pulse rounded-xl flex items-center justify-center text-[10px] text-stone-400 font-mono ${className}`}
            >
                Generating QR...
            </div>
        );
    }

    return (
        <img
            src={svgDataUrl}
            alt={`QR Code for ${value}`}
            width={size}
            height={size}
            style={{ width: size, height: size }}
            className={`shrink-0 block object-contain ${className}`}
        />
    );
};

export default QRCodeSVG;
