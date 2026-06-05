import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeWatcher } from './theme';
import { BrandPreviewWatcher } from './utils/brandPreview';

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

        // Inertia React's <App> calls the children render-prop with
        // { Component, key, props: pageProps }. We re-implement the default
        // page-with-layout resolution so we can mount <ThemeWatcher /> as a
        // sibling of the page (it needs to live inside Inertia's PageContext
        // so usePage() works, but outside any single page so it survives SPA
        // navigations).
        root.render(
            <App {...props}>
                {({ Component, key, props: pageProps }) => {
                    const child = <Component key={key} {...pageProps} />;
                    let rendered = child;
                    if (typeof Component.layout === 'function') {
                        rendered = Component.layout(child);
                    } else if (Array.isArray(Component.layout)) {
                        rendered = Component.layout
                            .concat(child)
                            .reverse()
                            .reduce((children, Layout) => (
                                <Layout {...pageProps}>{children}</Layout>
                            ));
                    }

                    return (
                        <>
                            <ThemeWatcher />
                            <BrandPreviewWatcher />
                            {rendered}
                        </>
                    );
                }}
            </App>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
