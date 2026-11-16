/**
 * Resolves dotted keys against the strings the Blade shell ships in `data-strings`
 * (pdv::ui lang file, so hosts can override via `pdv-lang` publish).
 */
export function createTranslator(strings = {}) {
    return function t(key, fallback = key) {
        const value = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), strings);

        return typeof value === 'string' ? value : fallback;
    };
}
