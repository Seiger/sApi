/**
 * Global declarations for sApi JS utilities.
 * This file provides IDE typings for functions defined inside Blade/vendor views,
 * so PhpStorm can resolve and typecheck calls across Blade includes.
 */
/*
|--------------------------------------------------------------------------
| views/scripts/global.blade.php
|--------------------------------------------------------------------------
*/

/** Allowed HTTP methods (uppercase). */
type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD' | 'OPTIONS';

/**
 * Lightweight fetch wrapper with typed response by `type`.
 * - Default behavior in your implementation maps to 'json'.
 * - Returns `null` when request fails (caught error or non-OK mapped error).
 *
 * @param url    Request URL (string | URL | Request)
 * @param data   Request body (FormData / object / string / URLSearchParams / etc.)
 * @param method HTTP method (default 'POST')
 * @param type   Desired response type (default 'json')
 */
declare function callApi(url: string | URL | Request, data?: BodyInit | object | null, method?: HttpMethod, type?: 'text'): Promise<string | null>;
declare function callApi<T = any>(url: string | URL | Request, data?: BodyInit | object | null, method?: HttpMethod, type?: 'json'): Promise<T | null>;
declare function callApi(url: string | URL | Request, data?: BodyInit | object | null, method?: HttpMethod, type?: 'blob'): Promise<Blob | null>;
declare function callApi(url: string | URL | Request, data?: BodyInit | object | null, method?: HttpMethod, type?: 'formData'): Promise<FormData | null>;
declare function callApi(url: string | URL | Request, data?: BodyInit | object | null, method?: HttpMethod, type?: 'arrayBuffer'): Promise<ArrayBuffer | null>;
declare function callApi<T = any>(url: string | URL | Request, data?: BodyInit | object | null, method?: HttpMethod, type?: undefined): Promise<T | null>;
