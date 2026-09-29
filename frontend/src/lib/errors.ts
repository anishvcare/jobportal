import { isAxiosError } from "axios";

interface LaravelError {
  message?: string;
  errors?: Record<string, string[]>;
}

/** Turns any API/network error into a short, friendly sentence. */
export function errorMessage(error: unknown, fallback = "Something went wrong. Please try again."): string {
  if (isAxiosError<LaravelError>(error)) {
    if (!error.response) {
      return typeof navigator !== "undefined" && !navigator.onLine
        ? "You appear to be offline. Check your connection and try again."
        : "We couldn't reach the server. Please try again in a moment.";
    }

    const { status, data } = error.response;
    const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;

    if (status === 422 && firstFieldError) return firstFieldError;
    if (status === 429) return "Too many attempts. Please wait a minute and try again.";
    if (status === 401) return "Please sign in to continue.";
    if (status === 403) return data?.message ?? "You don't have access to this.";
    if (status >= 500) return "The server had a problem. Please try again shortly.";
    return data?.message ?? fallback;
  }
  return fallback;
}

export function httpStatus(error: unknown): number | undefined {
  return isAxiosError(error) ? error.response?.status : undefined;
}
