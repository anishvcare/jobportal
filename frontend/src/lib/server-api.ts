import "server-only";
import { API_URL } from "./config";
import type { JobDetail, JobListItem, JobsMeta, JobSearchParams, Lookups } from "./types";

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

/**
 * Like publicGet, but also returns the `meta` envelope for list endpoints
 * (e.g. pagination). Returns null on any error/non-2xx so pages still render.
 */
export async function publicGetEnvelope<T, M = unknown>(
  path: string,
  revalidate = 300,
): Promise<{ data: T; meta: M } | null> {
  try {
    const response = await fetch(`${API_URL}/api/public/${path.replace(/^\//, "")}`, {
      headers: { Accept: "application/json" },
      next: { revalidate },
    });
    if (!response.ok) return null;
    const json = (await response.json()) as { data: T; meta: M };
    return { data: json.data, meta: json.meta };
  } catch {
    return null;
  }
}

export function getLookups(): Promise<Lookups | null> {
  return publicGet<Lookups>("lookups", 3600);
}

/** Serialises job search params into a querystring, dropping empty values. */
function jobsQuery(params: JobSearchParams): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null) continue;
    const str = String(value).trim();
    if (str !== "") search.set(key, str);
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/**
 * Fetch a page of live jobs from the public board. Returns both the cards and
 * pagination meta. Revalidated ~60s so new/closed jobs surface quickly.
 */
export async function getJobs(
  params: JobSearchParams = {},
  revalidate = 60,
): Promise<{ data: JobListItem[]; meta: JobsMeta } | null> {
  return publicGetEnvelope<JobListItem[], JobsMeta>(`jobs${jobsQuery(params)}`, revalidate);
}

/** Fetch a single live job by slug, or null when it is missing/not live (404). */
export function getJob(slug: string, revalidate = 60): Promise<JobDetail | null> {
  return publicGet<JobDetail>(`jobs/${encodeURIComponent(slug)}`, revalidate);
}
