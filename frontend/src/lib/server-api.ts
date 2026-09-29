import "server-only";
import { API_URL } from "./config";
import type { Lookups } from "./types";

/**
 * Server-side fetch for PUBLIC API endpoints (no cookies are forwarded).
 * Returns null instead of throwing so pages still render if the API is down.
 */
export async function publicGet<T>(path: string, revalidate = 300): Promise<T | null> {
  try {
    const response = await fetch(`${API_URL}/api/public/${path.replace(/^\//, "")}`, {
      headers: { Accept: "application/json" },
      next: { revalidate },
    });
    if (!response.ok) return null;
    const json = (await response.json()) as { data: T };
    return json.data;
  } catch {
    return null;
  }
}

export function getLookups(): Promise<Lookups | null> {
  return publicGet<Lookups>("lookups", 3600);
}
