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
