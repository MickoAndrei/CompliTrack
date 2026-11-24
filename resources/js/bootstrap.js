import axios from 'axios';
import { installRequestTiming } from './page-work';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// The XSRF cookie is shared by the browser. Use this document's session token.
window.axios.defaults.xsrfCookieName = null;
window.axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content;
window.axios.interceptors.request.use(config => {
    if (config.url && window.tabUrl) config.url = window.tabUrl(config.url);
    return config;
});
installRequestTiming(axios);
