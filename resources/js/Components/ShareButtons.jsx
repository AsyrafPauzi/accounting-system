import React from 'react';

const btn = 'inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream text-ink';

export default function ShareButtons({ publicUrl, whatsappUrl, className }) {
    if (!publicUrl && !whatsappUrl) {
        return null;
    }

    const cls = className || btn;

    const copy = async () => {
        if (!publicUrl) return;
        try {
            await navigator.clipboard.writeText(publicUrl);
        } catch {
            // ignore
        }
    };

    return (
        <>
            {whatsappUrl && (
                <a href={whatsappUrl} target="_blank" rel="noreferrer" className={cls}>WhatsApp</a>
            )}
            {publicUrl && (
                <button type="button" className={cls} onClick={copy}>Copy link</button>
            )}
        </>
    );
}
