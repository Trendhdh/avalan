/**
 * Avalan SmartPay (demo) — API Client
 * ---------------------------------------------------------------
 * The ONE place that knows how to talk to the demo backend. Structurally
 * this is the same client production uses (assets/api-client.js there):
 * one shared fetch() wrapper, a normalized ApiError shape, an
 * Authorization: Bearer header attached automatically once logged in.
 *
 * What's DIFFERENT from production, and why:
 *   - Production never calls the real backend host directly from the
 *     browser — it goes through this frontend's own api/proxy.php so a
 *     secret app-key header can be attached server-side (see that
 *     file's docblock). This demo backend has no real secret to hide
 *     (X-Avalan-Demo-Key is a public placeholder, see backend/.env.example),
 *     so the browser calls it directly — one less moving part for a
 *     reviewer to stand up.
 *   - No silent-refresh-on-401 loop: the demo has exactly one fixture
 *     user and one long-lived demo token, so there is nothing to
 *     refresh. A 401 just sends the user back to the login page.
 */
(function (global) {
    'use strict';

    const API_BASE = window.AVALAN_DEMO_API_BASE || 'http://127.0.0.1:8091';
    const APP_KEY = 'avalan-demo-app-key'; // public placeholder — see backend/.env.example
    const TOKEN_STORAGE_KEY = 'avalan_demo_token';
    const REQUEST_TIMEOUT_MS = 15000;

    class ApiError extends Error {
        constructor(message, { status = 0, code = 'unknown_error' } = {}) {
            super(message);
            this.name = 'ApiError';
            this.status = status;
            this.code = code;
        }
    }

    function getToken() {
        return localStorage.getItem(TOKEN_STORAGE_KEY);
    }

    function setToken(token) {
        localStorage.setItem(TOKEN_STORAGE_KEY, token);
    }

    function clearToken() {
        localStorage.removeItem(TOKEN_STORAGE_KEY);
    }

    function timeoutSignal(ms) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), ms);
        return { signal: controller.signal, cancel: () => clearTimeout(timer) };
    }

    async function request(path, { method = 'GET', auth = true } = {}) {
        const headers = { 'X-Avalan-Demo-Key': APP_KEY };
        if (auth) {
            const token = getToken();
            if (token) headers['Authorization'] = `Bearer ${token}`;
        }

        const { signal, cancel } = timeoutSignal(REQUEST_TIMEOUT_MS);
        let response;
        try {
            response = await fetch(`${API_BASE}${path}`, { method, headers, signal });
        } catch (err) {
            cancel();
            throw new ApiError('Demo backendga ulanib boʼlmadi. Server ishga tushirilganmi?', { status: 0, code: 'network_error' });
        }
        cancel();

        const payload = await response.json().catch(() => null);

        if (response.status === 401) {
            clearToken();
            global.location.href = 'index.php';
            throw new ApiError('Sessiya tugagan.', { status: 401, code: 'unauthorized' });
        }

        if (!response.ok) {
            throw new ApiError(payload?.message || payload?.error || 'Soʼrov bajarilmadi', { status: response.status, code: payload?.error || 'request_failed' });
        }

        return payload;
    }

    const AvalanDemoApi = {
        ApiError,
        getToken,
        clearToken,
        isAuthenticated: () => !!getToken(),

        async login() {
            const result = await request('/api/demo/auth/login', { method: 'POST', auth: false });
            setToken(result.access_token);
            return result;
        },
        logout() {
            clearToken();
        },
        balance: () => request('/api/demo/balance'),
        loans: () => request('/api/demo/loans'),
        smartpayCompute: () => request('/api/demo/smartpay/compute'),
        profile: () => request('/api/demo/profile'),
    };

    global.AvalanDemoApi = AvalanDemoApi;
})(window);
