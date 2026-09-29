import { API_URL } from "./config";
import type { Role, User } from "./types";

/** Only same-app relative paths are allowed as post-login destinations. */
export function safeRedirect(value: string | null | undefined): string | null {
  if (!value || value.length > 512) return null;
  if (!value.startsWith("/") || value.startsWith("//") || value.includes("\\")) return null;
  if (/[\x00-\x1F\x7F]/.test(value)) return null;
  return value;
}

/**
 * Full-page navigation to the API's Google redirect. Popups are avoided on
 * purpose: they break inside installed PWAs (especially iOS standalone mode).
 */
export function googleLoginUrl(redirect?: string | null): string {
  const url = new URL(`${API_URL}/auth/google/redirect`);
  const safe = safeRedirect(redirect);
  if (safe) url.searchParams.set("redirect", safe);
  return url.toString();
}

export const ROLE_HOME: Record<Role, string> = {
  candidate: "/candidate",
  employer: "/employer",
  admin: "/admin",
};

export function homeFor(user: User): string {
  if (user.needs_onboarding || !user.role) return "/auth/choose-role";
  return ROLE_HOME[user.role];
}

/** Remembers which CTA ("I'm a candidate/employer") the visitor clicked before sign-in. */
const INTENT_KEY = "nf_role_intent";

export function rememberRoleIntent(role: "candidate" | "employer"): void {
  try {
    sessionStorage.setItem(INTENT_KEY, role);
  } catch {
    /* storage unavailable (private mode); intent is optional */
  }
}

export function readRoleIntent(): "candidate" | "employer" | null {
  try {
    const value = sessionStorage.getItem(INTENT_KEY);
    return value === "candidate" || value === "employer" ? value : null;
  } catch {
    return null;
  }
}
