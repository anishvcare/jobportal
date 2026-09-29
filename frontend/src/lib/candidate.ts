"use client";

import useSWR, { mutate as globalMutate, type KeyedMutator } from "swr";
import { api, fetcher } from "./api";
import type {
  CandidateProfile,
  Completeness,
  DistrictOption,
  Education,
  Experience,
  Lookups,
  StateOption,
} from "./types";

export const PROFILE_KEY = "/candidate/profile";
export const LOOKUPS_KEY = "/public/lookups";

/** Fields the candidate can autosave on the profile via PATCH. */
export interface ProfilePatch {
  full_name?: string | null;
  dob?: string | null;
  gender?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  address?: string | null;
  city?: string | null;
  pincode?: string | null;
  country_id?: number | null;
  state_id?: number | null;
  district_id?: number | null;
  has_passport?: boolean;
  passport_number?: string | null;
  passport_expiry?: string | null;
  summary?: string | null;
  wizard_step?: number;
  consent?: boolean;
}

export interface EducationInput {
  education_level_id?: number | null;
  institution?: string | null;
  field_of_study?: string | null;
  year_completed?: number | null;
}

export interface ExperienceInput {
  job_title: string;
  company?: string | null;
  job_category_id?: number | null;
  start_date?: string | null;
  end_date?: string | null;
  is_current?: boolean;
  description?: string | null;
}

export interface LanguageInput {
  id: number;
  proficiency: string | null;
}

interface UseProfileResult {
  profile: CandidateProfile | undefined;
  isLoading: boolean;
  error: unknown;
  mutate: KeyedMutator<CandidateProfile>;
}

/** SWR hook for the candidate's own profile (auto-created server-side). */
export function useProfile(): UseProfileResult {
  const { data, error, isLoading, mutate } = useSWR<CandidateProfile>(
    PROFILE_KEY,
    fetcher<CandidateProfile>,
    { revalidateOnFocus: false },
  );
  return { profile: data, isLoading, error, mutate };
}

/** SWR hook for the public lookup lists (countries, trades, education levels, languages). */
export function useLookups() {
  const { data, error, isLoading } = useSWR<Lookups>(LOOKUPS_KEY, fetcher<Lookups>, {
    revalidateOnFocus: false,
    dedupingInterval: 60_000,
  });
  return { lookups: data, isLoading, error };
}

/** Refresh the SWR-cached profile from a fresh server payload. */
function writeProfileCache(profile: CandidateProfile): void {
  void globalMutate(PROFILE_KEY, profile, { revalidate: false });
}

/** Partial autosave: PATCH only the provided fields plus wizard_step; updates the cache. */
export async function patchProfile(patch: ProfilePatch): Promise<CandidateProfile> {
  const { data } = await api.patch<{ data: CandidateProfile }>(PROFILE_KEY, patch);
  writeProfileCache(data.data);
  return data.data;
}

export async function createEducation(input: EducationInput): Promise<CandidateProfile | Education> {
  const { data } = await api.post<{ data: Education }>(`${PROFILE_KEY}/educations`, input);
  void globalMutate(PROFILE_KEY);
  return data.data;
}

export async function updateEducation(id: number, input: EducationInput): Promise<Education> {
  const { data } = await api.patch<{ data: Education }>(`${PROFILE_KEY}/educations/${id}`, input);
  void globalMutate(PROFILE_KEY);
  return data.data;
}

export async function deleteEducation(id: number): Promise<void> {
  await api.delete(`${PROFILE_KEY}/educations/${id}`);
  void globalMutate(PROFILE_KEY);
}

export async function createExperience(input: ExperienceInput): Promise<Experience> {
  const { data } = await api.post<{ data: Experience }>(`${PROFILE_KEY}/experiences`, input);
  void globalMutate(PROFILE_KEY);
  return data.data;
}

export async function updateExperience(id: number, input: ExperienceInput): Promise<Experience> {
  const { data } = await api.patch<{ data: Experience }>(`${PROFILE_KEY}/experiences/${id}`, input);
  void globalMutate(PROFILE_KEY);
  return data.data;
}

export async function deleteExperience(id: number): Promise<void> {
  await api.delete(`${PROFILE_KEY}/experiences/${id}`);
  void globalMutate(PROFILE_KEY);
}

/** Create-on-the-fly skills: send the full array of skill names. */
export async function syncSkills(skills: string[]): Promise<CandidateProfile> {
  const { data } = await api.put<{ data: CandidateProfile }>(`${PROFILE_KEY}/skills`, { skills });
  writeProfileCache(data.data);
  return data.data;
}

export async function syncLanguages(languages: LanguageInput[]): Promise<CandidateProfile> {
  const { data } = await api.put<{ data: CandidateProfile }>(`${PROFILE_KEY}/languages`, { languages });
  writeProfileCache(data.data);
  return data.data;
}

export async function syncPreferredCategories(categoryIds: number[]): Promise<CandidateProfile> {
  const { data } = await api.put<{ data: CandidateProfile }>(`${PROFILE_KEY}/preferred-categories`, {
    category_ids: categoryIds,
  });
  writeProfileCache(data.data);
  return data.data;
}

export async function syncPreferredCountries(countryIds: number[]): Promise<CandidateProfile> {
  const { data } = await api.put<{ data: CandidateProfile }>(`${PROFILE_KEY}/preferred-countries`, {
    country_ids: countryIds,
  });
  writeProfileCache(data.data);
  return data.data;
}

/** Structured location lookups (only fetched when needed). */
export async function fetchStates(countryId: number): Promise<StateOption[]> {
  return fetcher<StateOption[]>(`/public/countries/${countryId}/states`);
}

export async function fetchDistricts(stateId: number): Promise<DistrictOption[]> {
  return fetcher<DistrictOption[]>(`/public/states/${stateId}/districts`);
}

/** SWR hook for a country's states; pass null to skip fetching. */
export function useStates(countryId: number | null) {
  const { data, isLoading } = useSWR<StateOption[]>(
    countryId ? `/public/countries/${countryId}/states` : null,
    fetcher<StateOption[]>,
    { revalidateOnFocus: false },
  );
  return { states: data ?? [], isLoading };
}

/** SWR hook for a state's districts; pass null to skip fetching. */
export function useDistricts(stateId: number | null) {
  const { data, isLoading } = useSWR<DistrictOption[]>(
    stateId ? `/public/states/${stateId}/districts` : null,
    fetcher<DistrictOption[]>,
    { revalidateOnFocus: false },
  );
  return { districts: data ?? [], isLoading };
}

/** Hard-delete the candidate's account and all data. */
export async function deleteAccount(): Promise<void> {
  await api.delete("/candidate/account");
}

/** Fetch the standalone completeness payload. */
export async function fetchCompleteness(): Promise<Completeness> {
  return fetcher<Completeness>(`${PROFILE_KEY}/completeness`);
}

/**
 * Fetch a PDF from the given candidate endpoint as a blob and trigger a browser
 * download with the supplied filename. Uses the shared api client so cookies,
 * XSRF handling and the 419 retry all apply. Resolves once the download has been
 * kicked off; the temporary object URL is revoked afterwards.
 */
async function downloadPdf(path: string, filename: string): Promise<void> {
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

/** Download the candidate's auto-generated résumé PDF (GET /candidate/resume). */
export function downloadResume(): Promise<void> {
  return downloadPdf("/candidate/resume", "resume.pdf");
}

/** Download the candidate's full Candidate Pack PDF (GET /candidate/pack). */
export function downloadCandidatePack(): Promise<void> {
  return downloadPdf("/candidate/pack", "candidate-pack.pdf");
}
