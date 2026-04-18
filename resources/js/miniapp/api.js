import axios from 'axios';
import { webApp } from './telegram.js';

const api = axios.create({
    baseURL: '/api/miniapp',
    timeout: 30000,
});

api.interceptors.request.use((config) => {
    config.headers = config.headers || {};
    config.headers['X-Telegram-Init-Data'] = webApp.initData || '';
    config.headers['Accept'] = 'application/json';
    return config;
});

api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response && err.response.status === 401) {
            window.dispatchEvent(new CustomEvent('miniapp:unauthorized'));
        }
        return Promise.reject(err);
    },
);

export default api;
