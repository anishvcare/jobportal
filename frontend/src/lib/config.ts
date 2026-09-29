/**
 * Public runtime configuration. Only NEXT_PUBLIC_* values belong here;
 * they are inlined into the client bundle, so never put secrets in them.
 */
export const API_URL = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/$/, "");

export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/$/, "");

export const APP_NAME = "Nexus Flow";
