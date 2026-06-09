import { useEffect } from 'react';

/**
 * Hidden bot-defence fields. Drop this inside any guest <form>.
 *
 *   <SpamBotFields data={data} setData={setData} botGuard={botGuard} />
 *
 * Pairs with App\Http\Middleware\SpamBotGuard on the backend:
 *
 *  - `_hp_url`    honeypot. Visually hidden, but real (so headless bots
 *                 that enumerate inputs will fill it). Field name uses
 *                 "url" not "email"/"name"/"address" because Chrome's
 *                 autofill heuristic aggressively fills any input named
 *                 *email* with the user's saved address even with
 *                 `autocomplete="off"` set. URL-named fields aren't
 *                 autofilled, which keeps the signal clean.
 *  - `_hp_ts`     encrypted render timestamp. The middleware requires
 *                 ≥800 ms between this token's mint time and POST receipt.
 *
 * If either signal trips, the request is rejected silently (generic 422
 * shaped like a normal validation error) so attackers can't tune around
 * it. Real users never see anything.
 *
 * Both fields must live in useForm `data` — Inertia posts only that object,
 * not native <input> values, so `_hp_ts` is synced via setData on mount.
 */
export default function SpamBotFields({ data, setData, botGuard }) {
    const ts = botGuard?.ts ?? '';

    useEffect(() => {
        if (ts && setData) {
            setData('_hp_ts', ts);
        }
    }, [ts, setData]);

    return (
        <>
            {/* Honeypot. Off-screen via CSS rather than `display:none` —
                some bots skip display:none inputs. tabIndex={-1} and
                aria-hidden keep it out of accessible navigation. The
                generic name "Leave blank" gives screen readers a clue
                while not being a meaningful autofill target. */}
            <div
                aria-hidden="true"
                style={{
                    position: 'absolute',
                    left: '-10000px',
                    top: 'auto',
                    width: '1px',
                    height: '1px',
                    overflow: 'hidden',
                }}
            >
                <label htmlFor="_hp_url">Leave this field empty</label>
                <input
                    id="_hp_url"
                    type="text"
                    name="_hp_url"
                    tabIndex={-1}
                    autoComplete="off"
                    value={data?._hp_url ?? ''}
                    onChange={(e) => setData?.('_hp_url', e.target.value)}
                />
            </div>

            {/* Encrypted render timestamp. Bound to useForm data so Inertia
                includes it in the POST body (DOM-only inputs are ignored). */}
            <input type="hidden" name="_hp_ts" value={data?._hp_ts ?? ts} readOnly />
        </>
    );
}
