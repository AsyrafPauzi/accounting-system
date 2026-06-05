import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeWatcher } from './theme';

const appName = import.meta.env.VITE_APP_NAME || 'BukuCloud';

// 419 = CSRF/session mismatch. The session is no longer valid, so reload the
// login page to get a clean session and fresh XSRF-TOKEN cookie. A plain
// window.location.reload() would just re-render the current page and leave the
// user stuck (they'd have to retry manually), so we navigate to /login instead.
router.on('invalid', (event) => {
    if (event.detail.response?.status === 419) {
        event.preventDefault();
        window.location.href = '/login';
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <App {...props}>
                {(page) => (
                    <>
                        <ThemeWatcher />
                        {page}
                    </>
                )}
            </App>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
