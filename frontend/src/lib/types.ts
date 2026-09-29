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
