/**
 * Error thrown for non-2xx responses. `errors` holds Laravel's 422 field bag.
 */
export class ApiError extends Error {
    constructor(message, status = 0, errors = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }

    /** First validation message, falling back to the generic message. */
    get firstError() {
        const first = Object.values(this.errors).flat()[0];

        return first || this.message;
    }
}

/**
 * Tiny fetch wrapper bound to the API base URL and the CSRF token.
 *
 * @param {{ base: string, csrf?: string, fallbackError?: string }} options
 */
export function createApi({ base, csrf = '', fallbackError = 'Error' }) {
    const root = base.replace(/\/+$/, '');

    async function request(method, path, { params, body } = {}) {
        const url = new URL(root + path, window.location.origin);

        Object.entries(params ?? {}).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.set(key, String(value));
            }
        });

        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (csrf) {
            headers['X-CSRF-TOKEN'] = csrf;
        }

        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
        }

        let response;

        try {
            response = await fetch(url, {
                method,
                headers,
                credentials: 'same-origin',
                body: body === undefined ? undefined : JSON.stringify(body),
            });
        } catch {
            throw new ApiError(fallbackError);
        }

        if (response.status === 204) {
            return null;
        }

        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            const message = payload?.message && response.status < 500 ? payload.message : fallbackError;

            throw new ApiError(message, response.status, payload?.errors ?? {});
        }

        return payload;
    }

    return {
        get: (path, params) => request('GET', path, { params }),
        post: (path, body) => request('POST', path, { body }),
        put: (path, body) => request('PUT', path, { body }),
        delete: (path) => request('DELETE', path),
    };
}
