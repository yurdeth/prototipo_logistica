// resources/js/lib/api.ts
// Cliente JSON para los endpoints de routes/api.php (autenticación JWT).
// El token se emite al iniciar sesión (middleware EnsureApiTokenCookie) y viaja
// en la cookie 'api_token'; aqui se lee y se envia como Authorization: Bearer.

export interface ApiError extends Error {
    status: number;
    errors?: Record<string, string[]>;
    datos?: unknown;
}

export const nombreCookieToken = 'api_token';

export const leerTokenDeCookie = (): string | null => {
    const par = document.cookie
        .split('; ')
        .find((fila) => fila.startsWith(`${nombreCookieToken}=`));

    return par ? decodeURIComponent(par.split('=').slice(1).join('=')) : null;
};

export async function apiJson<T>(
    url: string,
    opciones: { method?: string; body?: unknown } = {},
): Promise<T> {
    const token = leerTokenDeCookie();

    const respuesta = await fetch(url, {
        method: opciones.method ?? 'GET',
        headers: {
            Accept: 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...(opciones.body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: opciones.body ? JSON.stringify(opciones.body) : undefined,
    });

    const datos = (await respuesta.json().catch(() => null)) as
        | (T & { errors?: Record<string, string[]> })
        | null;

    if (!respuesta.ok) {
        const error = new Error(`HTTP ${respuesta.status}`) as ApiError;
        error.status = respuesta.status;
        error.errors = datos?.errors;
        error.datos = datos;
        throw error;
    }

    return datos as T;
}
