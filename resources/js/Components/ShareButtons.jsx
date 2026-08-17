import React from 'react';

const btn = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-warm bg-surface hover:bg-cream';

export default function ShareButtons({ publicUrl, whatsappUrl }) {
    if (!publicUrl && !whatsappUrl) {
        return null;
    }

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
                <a href={whatsappUrl} target="_blank" rel="noreferrer" className={btn}>WhatsApp</a>
            )}
            {publicUrl && (
                <button type="button" className={btn} onClick={copy}>Copy share link</button>
            )}
        </>
    );
}
