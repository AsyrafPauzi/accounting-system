import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Do NOT set X-CSRF-TOKEN here. Axios reads the XSRF-TOKEN cookie and sends it
// as X-XSRF-TOKEN automatically on every request, keeping it always in sync with
// the session. Setting X-CSRF-TOKEN from the meta tag would freeze it to the
// token at initial page load — after session()->regenerate() (e.g. on login) that
// stale value would mismatch the new session token and cause 419 on every POST.
