import { createApp } from 'vue';
import PdvApp from './vue/PdvApp.vue';
import { createApi } from './api.js';
import { createTranslator } from './i18n.js';
import '../css/pdv.css';

function readJson(element, attribute) {
    try {
        return JSON.parse(element.getAttribute(attribute) || '{}');
    } catch {
        return {};
    }
}

/**
 * Mounts the PDV on the element matching `selector`.
 *
 * Reads `data-page`, `data-api-base`, `data-csrf`, `data-routes` and `data-strings`
 * rendered by the package Blade shell. Returns the Vue app, or null when the element is absent.
 *
 * @param {string} selector
 */
export function bootPdv(selector = '#pdv-app') {
    const element = document.querySelector(selector);

    if (!element) {
        return null;
    }

    const t = createTranslator(readJson(element, 'data-strings'));
    const api = createApi({
        base: element.dataset.apiBase ?? '/pdv/api',
        csrf: element.dataset.csrf ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        fallbackError: t('common.error'),
    });

    const app = createApp(PdvApp, {
        page: element.dataset.page ?? 'counter',
        routes: readJson(element, 'data-routes'),
    });

    app.provide('pdvApi', api);
    app.provide('pdvT', t);
    app.mount(element);

    return app;
}

export { PdvApp };
