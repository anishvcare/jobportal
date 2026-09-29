"use client";

import useSWR, { type KeyedMutator } from "swr";
import { api, fetcher } from "./api";
import type {
  AdminCandidateDetail,
  BulkExport,
  CandidateSearchMeta,
  CandidateSummary,
  DownloadAudit,
  DownloadAuditKind,
  PaginationMeta,
} from "./types";

// Reuse the candidate lookup + location hooks for the admin filter form.
export { useDistricts, useLookups, useStates } from "./candidate";

/* ------------------------------------------------------------------ *
 * Candidate search                                                   *
 * ------------------------------------------------------------------ */

/** Filter/sort/pagination inputs for GET /admin/candidates. */
export interface CandidateSearchParams {
  keyword?: string;
  education_level_id?: number;
  skill_ids?: number[];
  experience_min?: number;
  experience_max?: number;
  job_category_id?: number;
  age_min?: number;
  age_max?: number;
  gender?: string;
  state_id?: number;
  district_id?: number;
  language_ids?: number[];
  passport?: "any" | "has" | "valid";
  completeness_min?: number;
  sort?: "name" | "age" | "experience" | "completeness" | "created_at";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
}

/** Build a stable querystring, dropping empty values and expanding arrays. */
export function buildSearchQuery(params: CandidateSearchParams): string {
  const search = new URLSearchParams();
  const keys = Object.keys(params).sort() as (keyof CandidateSearchParams)[];

  for (const key of keys) {
    const value = params[key];
    if (value === undefined || value === null || value === "") continue;
    if (Array.isArray(value)) {
      for (const item of value) search.append(`${key}[]`, String(item));
    } else {
      search.set(key, String(value));
    }
  }

  return search.toString();
}

interface SearchEnvelope {
  data: CandidateSummary[];
  meta: CandidateSearchMeta;
}

/** SWR fetcher that keeps the {data, meta} envelope (search needs meta too). */
async function searchFetcher(url: string): Promise<SearchEnvelope> {
  const { data } = await api.get<SearchEnvelope>(url);
  return { data: data.data, meta: data.meta };
}

interface UseCandidateSearchResult {
  results: CandidateSummary[];
  meta: CandidateSearchMeta | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<SearchEnvelope>;
}

/** SWR hook for the admin candidate search; exposes both results and meta. */
export function useCandidateSearch(params: CandidateSearchParams): UseCandidateSearchResult {
  const query = buildSearchQuery(params);
  const key = query ? `/admin/candidates?${query}` : "/admin/candidates";
  const { data, error, isLoading, mutate } = useSWR<SearchEnvelope>(key, searchFetcher, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  });

  return {
    results: data?.data ?? [],
    meta: data?.meta,
    isLoading,
    error,
    mutate,
  };
}

/* ------------------------------------------------------------------ *
 * Candidate detail                                                   *
 * ------------------------------------------------------------------ */

interface UseCandidateDetailResult {
  candidate: AdminCandidateDetail | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<AdminCandidateDetail>;
}

/** SWR hook for a single candidate's admin detail; pass null to skip. */
export function useCandidateDetail(id: number | null): UseCandidateDetailResult {
  const { data, error, isLoading, mutate } = useSWR<AdminCandidateDetail>(
    id ? `/admin/candidates/${id}` : null,
    fetcher<AdminCandidateDetail>,
    { revalidateOnFocus: false },
  );

  return { candidate: data, isLoading, error, mutate };
}

/* ------------------------------------------------------------------ *
 * Blob downloads (mirror the M3 downloadPdf helper)                  *
 * ------------------------------------------------------------------ */

/**
 * Fetch a private file from the given admin endpoint as a blob and trigger a
 * browser download with the supplied filename. Goes through the shared api
 * client so cookies, XSRF handling and the 419 retry all apply.
 */
export async function downloadFile(path: string, filename: string): Promise<void> {
  const { data } = await api.get<Blob>(path, { responseType: "blob" });
  const url = URL.createObjectURL(data);
  try {
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    link.rel = "noopener";
    document.body.appendChild(link);
    link.click();
    link.remove();
  } finally {
    URL.revokeObjectURL(url);
  }
}

/** Download a single candidate document (admin route). */
export function downloadAdminDocument(profileId: number, documentId: number, filename: string): Promise<void> {
  return downloadFile(`/admin/candidates/${profileId}/documents/${documentId}/download`, filename);
}

/** Download a candidate's résumé PDF (admin route). */
export function downloadAdminResume(profileId: number): Promise<void> {
  return downloadFile(`/admin/candidates/${profileId}/resume`, `resume-${profileId}.pdf`);
}

/** Download a candidate's full Candidate Pack PDF (admin route). */
export function downloadAdminPack(profileId: number): Promise<void> {
  return downloadFile(`/admin/candidates/${profileId}/pack`, `candidate-pack-${profileId}.pdf`);
}

/* ------------------------------------------------------------------ *
 * Bulk ZIP exports                                                   *
 * ------------------------------------------------------------------ */

/** Create a queued bulk export for up to 50 candidate ids. */
export async function createExport(candidateIds: number[]): Promise<BulkExport> {
  const { data } = await api.post<{ data: BulkExport }>("/admin/candidate-exports", {
    candidate_ids: candidateIds,
  });
  return data.data;
}

/** Fetch the current status/progress of an export (for polling). */
export async function getExport(id: number): Promise<BulkExport> {
  return fetcher<BulkExport>(`/admin/candidate-exports/${id}`);
}

/** Download a finished export ZIP. */
export function downloadExport(id: number): Promise<void> {
  return downloadFile(`/admin/candidate-exports/${id}/download`, `candidates-export-${id}.zip`);
}

/* ------------------------------------------------------------------ *
 * Download audit log                                                 *
 * ------------------------------------------------------------------ */

export interface AuditParams {
  kind?: DownloadAuditKind;
  candidate_profile_id?: number;
  per_page?: number;
  page?: number;
}

interface AuditEnvelope {
  data: DownloadAudit[];
  meta: PaginationMeta;
}

async function auditFetcher(url: string): Promise<AuditEnvelope> {
  const { data } = await api.get<AuditEnvelope>(url);
  return { data: data.data, meta: data.meta };
}

interface UseDownloadAuditsResult {
  audits: DownloadAudit[];
  meta: PaginationMeta | undefined;
  isLoading: boolean;
  error: unknown;
}

/** SWR hook for the paginated download-audit log. */
export function useDownloadAudits(params: AuditParams): UseDownloadAuditsResult {
  const search = new URLSearchParams();
  if (params.kind) search.set("kind", params.kind);
  if (params.candidate_profile_id) search.set("candidate_profile_id", String(params.candidate_profile_id));
  if (params.per_page) search.set("per_page", String(params.per_page));
  if (params.page) search.set("page", String(params.page));
  const query = search.toString();
  const key = query ? `/admin/download-audits?${query}` : "/admin/download-audits";

  const { data, error, isLoading } = useSWR<AuditEnvelope>(key, auditFetcher, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  });

  return { audits: data?.data ?? [], meta: data?.meta, isLoading, error };
}

/* ------------------------------------------------------------------ *
 * Shared helpers                                                     *
 * ------------------------------------------------------------------ */

/** The server-side per-export cap; selections are chunked into batches of this. */
export const BULK_EXPORT_MAX = 50;

/** Split a list of ids into chunks no larger than BULK_EXPORT_MAX. */
export function chunkForExport(ids: number[], size = BULK_EXPORT_MAX): number[][] {
  const chunks: number[][] = [];
  for (let i = 0; i < ids.length; i += size) {
    chunks.push(ids.slice(i, i + size));
  }
  return chunks;
}
