"use client";

import useSWR, { type KeyedMutator } from "swr";
import { api, downloadFile, fetcher } from "./api";
import type {
  AdminApplication,
  AdminCandidateDetail,
  AdminDashboard,
  AdminEmployer,
  AdminJob,
  ApplicationStatus,
  BulkExport,
  CandidateSearchMeta,
  CandidateSummary,
  DownloadAudit,
  DownloadAuditKind,
  EmployerStatus,
  JobStatus,
  PaginationMeta,
} from "./types";

// Re-export the shared blob-download helper so existing admin callers keep working.
export { downloadFile } from "./api";

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
  applied_job_id?: number;
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
 * Blob downloads (downloadFile lives in api.ts, re-exported above)   *
 * ------------------------------------------------------------------ */

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
  mutate: KeyedMutator<AuditEnvelope>;
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

  const { data, error, isLoading, mutate } = useSWR<AuditEnvelope>(key, auditFetcher, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  });

  return { audits: data?.data ?? [], meta: data?.meta, isLoading, error, mutate };
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

/* ------------------------------------------------------------------ *
 * Admin dashboard                                                    *
 * ------------------------------------------------------------------ */

/** SWR hook for the admin dashboard tiles. */
export function useAdminDashboard() {
  const { data, error, isLoading } = useSWR<AdminDashboard>("/admin/dashboard", fetcher<AdminDashboard>, {
    revalidateOnFocus: false,
  });
  return { dashboard: data, isLoading, error };
}

/** Build a simple querystring from a flat record, dropping empty values. */
function buildQuery(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") continue;
    search.set(key, String(value));
  }
  return search.toString();
}

/* ------------------------------------------------------------------ *
 * Employers management                                               *
 * ------------------------------------------------------------------ */

export interface EmployerListParams {
  status?: EmployerStatus;
  per_page?: number;
  page?: number;
}

interface EmployerEnvelope {
  data: AdminEmployer[];
  meta: PaginationMeta;
}

async function listFetcher<T>(url: string): Promise<{ data: T[]; meta: PaginationMeta }> {
  const { data } = await api.get<{ data: T[]; meta: PaginationMeta }>(url);
  return { data: data.data, meta: data.meta };
}

interface UseAdminEmployersResult {
  employers: AdminEmployer[];
  meta: PaginationMeta | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<EmployerEnvelope>;
}

/** SWR hook for the paginated admin employers list. */
export function useAdminEmployers(params: EmployerListParams): UseAdminEmployersResult {
  const query = buildQuery({ ...params });
  const key = query ? `/admin/employers?${query}` : "/admin/employers";
  const { data, error, isLoading, mutate } = useSWR<EmployerEnvelope>(key, listFetcher<AdminEmployer>, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  });
  return { employers: data?.data ?? [], meta: data?.meta, isLoading, error, mutate };
}

/** Approve an employer. */
export async function approveEmployer(id: number): Promise<AdminEmployer> {
  const { data } = await api.post<{ data: AdminEmployer }>(`/admin/employers/${id}/approve`, {});
  return data.data;
}

/** Suspend an employer. */
export async function suspendEmployer(id: number): Promise<AdminEmployer> {
  const { data } = await api.post<{ data: AdminEmployer }>(`/admin/employers/${id}/suspend`, {});
  return data.data;
}

/* ------------------------------------------------------------------ *
 * Jobs management                                                    *
 * ------------------------------------------------------------------ */

export interface AdminJobListParams {
  status?: JobStatus;
  keyword?: string;
  employer_profile_id?: number;
  per_page?: number;
  page?: number;
}

interface AdminJobEnvelope {
  data: AdminJob[];
  meta: PaginationMeta;
}

interface UseAdminJobsResult {
  jobs: AdminJob[];
  meta: PaginationMeta | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<AdminJobEnvelope>;
}

/** SWR hook for the paginated admin jobs list. */
export function useAdminJobs(params: AdminJobListParams): UseAdminJobsResult {
  const query = buildQuery({ ...params });
  const key = query ? `/admin/jobs?${query}` : "/admin/jobs";
  const { data, error, isLoading, mutate } = useSWR<AdminJobEnvelope>(key, listFetcher<AdminJob>, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  });
  return { jobs: data?.data ?? [], meta: data?.meta, isLoading, error, mutate };
}

async function jobAction(id: number, action: "hide" | "unhide" | "close"): Promise<AdminJob> {
  const { data } = await api.post<{ data: AdminJob }>(`/admin/jobs/${id}/${action}`, {});
  return data.data;
}

export const hideAdminJob = (id: number) => jobAction(id, "hide");
export const unhideAdminJob = (id: number) => jobAction(id, "unhide");
export const closeAdminJob = (id: number) => jobAction(id, "close");

/* ------------------------------------------------------------------ *
 * Applications management                                            *
 * ------------------------------------------------------------------ */

export interface AdminApplicationListParams {
  status?: ApplicationStatus;
  job_post_id?: number;
  candidate_profile_id?: number;
  per_page?: number;
  page?: number;
}

interface AdminApplicationEnvelope {
  data: AdminApplication[];
  meta: PaginationMeta;
}

interface UseAdminApplicationsResult {
  applications: AdminApplication[];
  meta: PaginationMeta | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<AdminApplicationEnvelope>;
}

/** SWR hook for the paginated admin applications list. */
export function useAdminApplications(params: AdminApplicationListParams): UseAdminApplicationsResult {
  const query = buildQuery({ ...params });
  const key = query ? `/admin/applications?${query}` : "/admin/applications";
  const { data, error, isLoading, mutate } = useSWR<AdminApplicationEnvelope>(key, listFetcher<AdminApplication>, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  });
  return { applications: data?.data ?? [], meta: data?.meta, isLoading, error, mutate };
}

/** Update the status of an application (admin route). */
export async function updateAdminApplicationStatus(id: number, status: ApplicationStatus): Promise<AdminApplication> {
  const { data } = await api.patch<{ data: AdminApplication }>(`/admin/applications/${id}/status`, { status });
  return data.data;
}
