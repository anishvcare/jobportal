export type Role = "candidate" | "employer" | "admin";

export interface User {
  id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  role: Role | null;
  needs_onboarding: boolean;
}

export interface ApiResource<T> {
  data: T;
}

export interface Country {
  id: number;
  name: string;
  iso2: string;
  has_states: boolean;
}

export interface NamedItem {
  id: number;
  name: string;
  slug?: string;
}

export interface EducationLevel extends NamedItem {
  rank: number;
}

export interface Language {
  id: number;
  name: string;
  code: string;
}

/** A category group (Skilled / Unskilled) with its trades. */
export interface CategoryGroup extends NamedItem {
  trades: NamedItem[];
}

export interface Lookups {
  countries: Country[];
  job_categories: CategoryGroup[];
  education_levels: EducationLevel[];
  languages: Language[];
}

export interface AdminDashboard {
  candidates: number;
  employers: number;
  pending_onboarding: number;
}

/** A state/province returned by the lookup endpoint for a country. */
export interface StateOption {
  id: number;
  name: string;
  type: string;
}

/** A district returned by the lookup endpoint for a state. */
export interface DistrictOption {
  id: number;
  name: string;
}

export type Gender = "male" | "female" | "other";

export type LanguageProficiency = "basic" | "conversational" | "fluent" | "native";

export interface Education {
  id: number;
  education_level_id: number | null;
  institution: string | null;
  field_of_study: string | null;
  year_completed: number | null;
  sort_order: number;
}

export interface Experience {
  id: number;
  job_title: string;
  company: string | null;
  job_category_id: number | null;
  start_date: string | null;
  end_date: string | null;
  is_current: boolean;
  description: string | null;
  sort_order: number;
}

/** A skill attached to the profile ({id, name}). */
export interface ProfileSkill {
  id: number;
  name: string;
}

/** A language attached to the profile, with the candidate's proficiency. */
export interface ProfileLanguage {
  id: number;
  name: string;
  code: string;
  proficiency: LanguageProficiency | null;
}

export type DocumentType =
  | "photo"
  | "aadhaar_front"
  | "aadhaar_back"
  | "sslc"
  | "education_cert"
  | "skill_cert"
  | "experience_cert"
  | "passport"
  | "cv"
  | "profile_pdf";

export interface DocumentDto {
  id: number;
  type: DocumentType;
  original_name: string;
  mime: string;
  size: number;
  page_count: number | null;
  sort_order: number;
  created_at: string | null;
  /** Relative API path, e.g. "/api/candidate/documents/12/download". */
  download_url: string;
}

export interface Completeness {
  percentage: number;
  missing: string[];
  required: string[];
  has_profile_pdf: boolean;
  pack_ready: boolean;
}

/* ------------------------------------------------------------------ *
 * Public job board (Milestone 5): listing, detail, search + JSON-LD  *
 * ------------------------------------------------------------------ */

/** A compact job card on the public listing page (matches JobListResource). */
export interface JobListItem {
  id: number;
  title: string;
  slug: string;
  company_name: string | null;
  logo_url: string | null;
  category: string | null;
  location: string | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  experience_min: number | null;
  experience_max: number | null;
  deadline: string | null;
  published_at: string | null;
}

/** hiringOrganization block on the job detail (matches JobDetailResource). */
export interface JobHiringOrganization {
  name: string | null;
  logo_url: string | null;
  website: string | null;
}

/** jobLocation block on the job detail (matches JobDetailResource). */
export interface JobLocation {
  city: string | null;
  district: string | null;
  state: string | null;
  country: string | null;
}

/** baseSalary block on the job detail (matches JobDetailResource). */
export interface JobBaseSalary {
  min: number | null;
  max: number | null;
  currency: string | null;
}

/** Full public job detail (matches JobDetailResource, incl. JSON-LD fields). */
export interface JobDetail {
  id: number;
  title: string;
  slug: string;
  description: string;
  date_posted: string | null;
  valid_through: string | null;
  employment_type: string;
  hiring_organization: JobHiringOrganization;
  job_location: JobLocation;
  base_salary: JobBaseSalary;
  category: string | null;
  category_slug: string | null;
  education_level: string | null;
  experience_min: number | null;
  experience_max: number | null;
  vacancies: number | null;
  skills: ProfileSkill[];
  deadline: string | null;
  published_at: string | null;
}

/** Pagination meta returned by GET /api/public/jobs. */
export interface JobsMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/** Query parameters accepted by the public job board. */
export interface JobSearchParams {
  keyword?: string;
  country_id?: string | number;
  state_id?: string | number;
  district_id?: string | number;
  job_category_id?: string | number;
  category?: string;
  experience?: string | number;
  salary_min?: string | number;
  per_page?: string | number;
  page?: string | number;
}

/* ------------------------------------------------------------------ *
 * Admin (Milestone 4): candidate search, detail, bulk export, audits *
 * ------------------------------------------------------------------ */

/** Generic pagination meta returned alongside admin list endpoints. */
export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/** One row in the admin candidate search results. */
export interface CandidateSummary {
  id: number;
  full_name: string | null;
  age: number | null;
  gender: Gender | null;
  district: string | null;
  trades: string[];
  experience_years: number;
  completeness: number;
  has_passport: boolean;
  passport_valid: boolean;
  /** Relative API path (e.g. "/api/admin/candidates/12/photo") or null. */
  photo_url: string | null;
}

/** A per-trade count over the whole filtered result set. */
export interface TradeCount {
  trade_id: number;
  trade_name: string;
  count: number;
}

/** Meta returned by GET /admin/candidates: pagination + per-trade counts. */
export interface CandidateSearchMeta extends PaginationMeta {
  trade_counts: TradeCount[];
}

export interface AdminNamedRef {
  id: number;
  name: string;
}

export interface AdminEducation {
  id: number;
  institution: string | null;
  field_of_study: string | null;
  year_completed: number | null;
  education_level: (AdminNamedRef & { rank: number }) | null;
}

export interface AdminExperience {
  id: number;
  job_title: string;
  company: string | null;
  start_date: string | null;
  end_date: string | null;
  is_current: boolean;
  job_category: AdminNamedRef | null;
}

export interface AdminProfileLanguage {
  id: number;
  name: string;
  code: string;
  proficiency: LanguageProficiency | null;
}

export interface AdminDocument {
  id: number;
  type: DocumentType;
  original_name: string;
  mime: string;
  size: number;
  page_count: number | null;
  /** Relative API path for the admin document download route. */
  download_url: string;
}

/** Full candidate detail for the admin candidate page. */
export interface AdminCandidateDetail {
  id: number;
  full_name: string | null;
  dob: string | null;
  age: number | null;
  gender: Gender | null;
  phone: string | null;
  whatsapp: string | null;
  address: string | null;
  city: string | null;
  pincode: string | null;
  summary: string | null;
  user: { id: number; name: string; email: string } | null;
  country: AdminNamedRef | null;
  state: AdminNamedRef | null;
  district: AdminNamedRef | null;
  has_passport: boolean;
  /** Decrypted by the backend (admin is authorized). */
  passport_number: string | null;
  passport_expiry: string | null;
  passport_valid: boolean;
  educations: AdminEducation[];
  experiences: AdminExperience[];
  skills: ProfileSkill[];
  languages: AdminProfileLanguage[];
  preferred_categories: AdminNamedRef[];
  preferred_countries: AdminNamedRef[];
  documents: AdminDocument[];
  photo_url: string | null;
  completeness: Completeness;
}

export type BulkExportStatus = "queued" | "processing" | "ready" | "failed";

/** A bulk ZIP export row the admin UI polls. */
export interface BulkExport {
  id: number;
  status: BulkExportStatus;
  progress: number;
  candidate_count: number;
  ready: boolean;
  expires_at: string | null;
  error: string | null;
  created_at: string | null;
  /** Relative API path for the ZIP; null until ready. */
  download_url: string | null;
}

export type DownloadAuditKind = "document" | "resume" | "pack" | "bulk_zip";

/** A single download-audit row. */
export interface DownloadAudit {
  id: number;
  actor: { id: number; name: string; email: string } | null;
  candidate_profile_id: number | null;
  /** null when the candidate was hard-deleted or the row is a bulk row. */
  candidate_name: string | null;
  kind: DownloadAuditKind;
  bulk_export_id: number | null;
  document_id: number | null;
  ip: string | null;
  created_at: string | null;
}

export interface CandidateProfile {
  id: number;
  full_name: string | null;
  dob: string | null;
  gender: Gender | null;
  phone: string | null;
  whatsapp: string | null;
  address: string | null;
  city: string | null;
  pincode: string | null;
  country_id: number | null;
  state_id: number | null;
  district_id: number | null;
  has_passport: boolean;
  passport_number: string | null;
  passport_expiry: string | null;
  summary: string | null;
  wizard_step: number;
  consent_at: string | null;
  consent_version: string | null;
  educations: Education[];
  experiences: Experience[];
  skills: ProfileSkill[];
  languages: ProfileLanguage[];
  preferred_categories: NamedItem[];
  preferred_countries: NamedItem[];
  documents: DocumentDto[];
  completeness: Completeness;
}
