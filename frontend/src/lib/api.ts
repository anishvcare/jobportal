import axios, { AxiosError, type InternalAxiosRequestConfig } from "axios";
import { API_URL } from "./config";

/**
 * Browser API client for the Laravel backend.
 * Uses Sanctum's cookie-based SPA auth: the session cookie and XSRF-TOKEN
 * cookie are set by the API (sibling subdomain) and sent with credentials.
 */
export const api = axios.create({
  baseURL: `${API_URL}/api`,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: "XSRF-TOKEN",
  xsrfHeaderName: "X-XSRF-TOKEN",
  headers: {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
  timeout: 30_000,
});

let csrfPromise: Promise<void> | null = null;

/** Fetches the XSRF-TOKEN cookie once per page load (or again after a 419). */
export function ensureCsrf(force = false): Promise<void> {
  if (force || !csrfPromise) {
    csrfPromise = axios
      .get(`${API_URL}/sanctum/csrf-cookie`, { withCredentials: true })
      .then(() => undefined)
      .catch((error: unknown) => {
        csrfPromise = null;
        throw error;
      });
  }
  return csrfPromise;
}

const MUTATING = new Set(["post", "put", "patch", "delete"]);

api.interceptors.request.use(async (config) => {
  if (MUTATING.has((config.method ?? "get").toLowerCase())) {
    await ensureCsrf();
  }
  return config;
});

type RetryableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean };

// 419 = CSRF token mismatch (e.g. session expired). Refresh the token and retry once.
api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config as RetryableConfig | undefined;
  if (error.response?.status === 419 && config && !config._csrfRetried) {
    config._csrfRetried = true;
    await ensureCsrf(true);
    return api.request(config);
  }
  throw error;
});

/** SWR fetcher that unwraps Laravel's `{ data }` resource envelope. */
export async function fetcher<T>(url: string): Promise<T> {
  const response = await api.get<{ data: T }>(url);
  return response.data.data;
}
