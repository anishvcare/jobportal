"use client";

import useSWR, { mutate as globalMutate, type KeyedMutator } from "swr";
import { api, downloadFile, fetcher } from "./api";
import type {
  Applicant,
  ApplicationStatus,
  EmployerJob,
  EmployerProfile,
  PaginationMeta,
} from "./types";

// Reuse the shared lookup + location hooks for the employer profile/job forms.
export { useDistricts, useLookups, useStates } from "./candidate";

export const EMPLOYER_PROFILE_KEY = "/employer/profile";
export const EMPLOYER_JOBS_KEY = "/employer/jobs";

/* ------------------------------------------------------------------ *
 * Company profile                                                    *
 * ------------------------------------------------------------------ */

interface UseEmployerProfileResult {
  profile: EmployerProfile | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<EmployerProfile>;
}

/** SWR hook for the employer's own company profile (auto-created server-side). */
export function useEmployerProfile(): UseEmployerProfileResult {
  const { data, error, isLoading, mutate } = useSWR<EmployerProfile>(
    EMPLOYER_PROFILE_KEY,
    fetcher<EmployerProfile>,
    { revalidateOnFocus: false },
  );
  return { profile: data, isLoading, error, mutate };
}

/** Fields the employer can PATCH on their profile (status is not settable). */
export interface EmployerProfilePatch {
  company_name?: string | null;
  contact_person?: string | null;
  phone?: string | null;
  website?: string | null;
  country_id?: number | null;
  state_id?: number | null;
  district_id?: number | null;
  city?: string | null;
}

function writeProfileCache(profile: EmployerProfile): void {
  void globalMutate(EMPLOYER_PROFILE_KEY, profile, { revalidate: false });
}

/** Update the employer profile; refreshes the SWR cache. */
export async function updateEmployerProfile(patch: EmployerProfilePatch): Promise<EmployerProfile> {
  const { data } = await api.patch<{ data: EmployerProfile }>(EMPLOYER_PROFILE_KEY, patch);
  writeProfileCache(data.data);
  return data.data;
}

/** Upload a new company logo (multipart); axios sets the boundary automatically. */
export async function uploadLogo(file: File): Promise<EmployerProfile> {
  const form = new FormData();
  form.append("logo", file);
  const { data } = await api.post<{ data: EmployerProfile }>(`${EMPLOYER_PROFILE_KEY}/logo`, form);
  writeProfileCache(data.data);
  return data.data;
}

/** Remove the company logo. */
export async function deleteLogo(): Promise<EmployerProfile> {
  const { data } = await api.delete<{ data: EmployerProfile }>(`${EMPLOYER_PROFILE_KEY}/logo`);
  writeProfileCache(data.data);
  return data.data;
}

/* ------------------------------------------------------------------ *
 * Jobs                                                               *
 * ------------------------------------------------------------------ */

interface JobEnvelope {
  data: EmployerJob[];
  meta: PaginationMeta;
}

async function jobsFetcher(url: string): Promise<JobEnvelope> {
  const { data } = await api.get<JobEnvelope>(url);
  return { data: data.data, meta: data.meta };
}

interface UseEmployerJobsResult {
  jobs: EmployerJob[];
  meta: PaginationMeta | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<JobEnvelope>;
}

/** SWR hook for the employer's own jobs (paginated). */
export function useEmployerJobs(page = 1, perPage = 50): UseEmployerJobsResult {
  const key = `${EMPLOYER_JOBS_KEY}?page=${page}&per_page=${perPage}`;
  const { data, error, isLoading, mutate } = useSWR<JobEnvelope>(key, jobsFetcher, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  });
  return { jobs: data?.data ?? [], meta: data?.meta, isLoading, error, mutate };
}

/** SWR hook for a single job the employer owns; pass null to skip. */
export function useEmployerJob(id: number | null) {
  const { data, error, isLoading, mutate } = useSWR<EmployerJob>(
    id ? `${EMPLOYER_JOBS_KEY}/${id}` : null,
    fetcher<EmployerJob>,
    { revalidateOnFocus: false },
  );
  return { job: data, isLoading, error, mutate };
}

/** Payload for creating/editing a job posting. */
export interface JobInput {
  title: string;
  description: string;
  job_category_id: number;
  country_id?: number | null;
  state_id?: number | null;
  district_id?: number | null;
  city?: string | null;
  education_level_id?: number | null;
  experience_min?: number | null;
  experience_max?: number | null;
  vacancies?: number | null;
  salary_min?: number | null;
  salary_max?: number | null;
  salary_currency?: string | null;
  deadline?: string | null;
  skill_ids?: number[];
  /** Free-text skill names, created on the fly (mirrors the candidate flow). */
  skills?: string[];
}

/** Create a new job posting. */
export async function createJob(input: JobInput): Promise<EmployerJob> {
  const { data } = await api.post<{ data: EmployerJob }>(EMPLOYER_JOBS_KEY, input);
  void globalMutate((key) => typeof key === "string" && key.startsWith(EMPLOYER_JOBS_KEY));
  return data.data;
}

/** Update an existing job posting (partial). */
export async function updateJob(id: number, input: Partial<JobInput>): Promise<EmployerJob> {
  const { data } = await api.patch<{ data: EmployerJob }>(`${EMPLOYER_JOBS_KEY}/${id}`, input);
  void globalMutate((key) => typeof key === "string" && key.startsWith(EMPLOYER_JOBS_KEY));
  return data.data;
}

/** Publish a draft job. Throws on 403 (pending approval) / 422 (deadline passed). */
export async function publishJob(id: number): Promise<EmployerJob> {
  const { data } = await api.post<{ data: EmployerJob }>(`${EMPLOYER_JOBS_KEY}/${id}/publish`, {});
  void globalMutate((key) => typeof key === "string" && key.startsWith(EMPLOYER_JOBS_KEY));
  return data.data;
}

/** Close a job. */
export async function closeJob(id: number): Promise<EmployerJob> {
  const { data } = await api.post<{ data: EmployerJob }>(`${EMPLOYER_JOBS_KEY}/${id}/close`, {});
  void globalMutate((key) => typeof key === "string" && key.startsWith(EMPLOYER_JOBS_KEY));
  return data.data;
}

/* ------------------------------------------------------------------ *
 * Applicants                                                         *
 * ------------------------------------------------------------------ */

interface ApplicantEnvelope {
  data: Applicant[];
  meta: PaginationMeta;
}

async function applicantsFetcher(url: string): Promise<ApplicantEnvelope> {
  const { data } = await api.get<ApplicantEnvelope>(url);
  return { data: data.data, meta: data.meta };
}

interface UseJobApplicantsResult {
  applicants: Applicant[];
  meta: PaginationMeta | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<ApplicantEnvelope>;
}

/** SWR hook for the applicants to one of the employer's jobs; pass null to skip. */
export function useJobApplicants(jobId: number | null, page = 1, perPage = 50): UseJobApplicantsResult {
  const key = jobId ? `${EMPLOYER_JOBS_KEY}/${jobId}/applicants?page=${page}&per_page=${perPage}` : null;
  const { data, error, isLoading, mutate } = useSWR<ApplicantEnvelope>(key, applicantsFetcher, {
    revalidateOnFocus: false,
    keepPreviousData: true,
  });
  return { applicants: data?.data ?? [], meta: data?.meta, isLoading, error, mutate };
}

/** Update an applicant's status (employer route). */
export async function updateApplicantStatus(
  applicationId: number,
  status: ApplicationStatus,
): Promise<Applicant> {
  const { data } = await api.patch<{ data: Applicant }>(
    `/employer/applications/${applicationId}/status`,
    { status },
  );
  return data.data;
}

/** Download an applicant's résumé PDF (employer route). Resume only. */
export function downloadApplicantResume(applicationId: number): Promise<void> {
  return downloadFile(`/employer/applications/${applicationId}/resume`, `resume-${applicationId}.pdf`);
}
