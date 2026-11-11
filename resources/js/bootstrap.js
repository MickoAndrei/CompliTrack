import axios from 'axios';
import { installRequestTiming } from './page-work';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
installRequestTiming(axios);
