import type { Metadata } from "next";
import Link from "next/link";
import { buttonClasses } from "@/components/ui/Button";
import { getJobs, getLookups } from "@/lib/server-api";
import type { JobListItem, JobSearchParams } from "@/lib/types";

export const metadata: Metadata = {
  title: "Jobs",
  description: "Browse live jobs in Kerala, India, Russia and worldwide.",
};

// New/closed jobs surface quickly; the board is server-rendered and cacheable.
export const revalidate = 60;

const PER_PAGE = 20;

/** First non-empty string value from a searchParams entry. */
function firstValue(value: string | string[] | undefined): string | undefined {
  if (Array.isArray(value)) return value[0];
  return value;
}

/**
 * Combined keyword for the board search. Uses explicit `keyword`/`q` first,
 * then appends any free-text `location` (from the home hero) so both terms
 * feed the single keyword filter the API supports.
 */
function keywordFromRaw(raw: Record<string, string | string[] | undefined>): string | undefined {
  const keyword = firstValue(raw.keyword) ?? firstValue(raw.q);
  const location = firstValue(raw.location);
  const parts = [keyword, location]
    .map((p) => p?.trim())
    .filter((p): p is string => !!p && p !== "");
  return parts.length ? parts.join(" ") : undefined;
}

/** Turns the raw searchParams into the params getJobs understands. */
function toSearchParams(raw: Record<string, string | string[] | undefined>): JobSearchParams {
  // The home hero exposes a free-text `location` field, but the backend has no
  // free-text location filter (location is structured: country/state/district).
  // Fold any `location` value into the keyword search so it is honoured rather
  // than silently dropped. Explicit `keyword`/`q` still take precedence.
  const keyword = keywordFromRaw(raw);
  const params: JobSearchParams = { per_page: PER_PAGE };
  if (keyword && keyword.trim() !== "") params.keyword = keyword.trim();
  const countryId = firstValue(raw.country_id);
  if (countryId) params.country_id = countryId;
  const stateId = firstValue(raw.state_id);
  if (stateId) params.state_id = stateId;
  const districtId = firstValue(raw.district_id);
  if (districtId) params.district_id = districtId;
  const category = firstValue(raw.category);
  if (category) params.category = category;
  const experience = firstValue(raw.experience);
  if (experience) params.experience = experience;
  const salaryMin = firstValue(raw.salary_min);
  if (salaryMin) params.salary_min = salaryMin;
  const page = firstValue(raw.page);
  if (page) params.page = page;
  return params;
}

/** Builds a shareable querystring for pagination links, preserving filters. */
function pageHref(base: Record<string, string | undefined>, page: number): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(base)) {
    if (value && value.trim() !== "") search.set(key, value);
  }
  search.set("page", String(page));
  return `/jobs?${search.toString()}`;
}

function formatSalary(job: JobListItem): string | null {
  const { salary_min, salary_max, salary_currency } = job;
  if (salary_min == null && salary_max == null) return null;
  const currency = salary_currency ?? "";
  const fmt = (n: number) => n.toLocaleString();
  if (salary_min != null && salary_max != null) return `${currency} ${fmt(salary_min)} – ${fmt(salary_max)}`.trim();
  if (salary_min != null) return `${currency} ${fmt(salary_min)}+`.trim();
  return `Up to ${currency} ${fmt(salary_max as number)}`.trim();
}

function formatExperience(job: JobListItem): string | null {
  const { experience_min, experience_max } = job;
  if (experience_min == null && experience_max == null) return null;
  if (experience_min != null && experience_max != null) return `${experience_min}–${experience_max} yrs exp`;
  if (experience_min != null) return `${experience_min}+ yrs exp`;
  return `Up to ${experience_max} yrs exp`;
}

function JobCard({ job }: { job: JobListItem }) {
  const salary = formatSalary(job);
  const experience = formatExperience(job);
  return (
    <Link
      href={`/jobs/${job.slug}`}
      className="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-colors hover:border-brand-300 hover:bg-brand-50/40 sm:p-5"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="truncate text-lg font-semibold text-slate-900">{job.title}</h2>
          <p className="mt-0.5 text-sm text-slate-600">
            {job.company_name ?? "Confidential employer"}
            {job.location ? ` · ${job.location}` : ""}
          </p>
        </div>
        {job.category && (
          <span className="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
            {job.category}
          </span>
        )}
      </div>
      <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
        {salary && <span>{salary}</span>}
        {experience && <span>{experience}</span>}
        {job.deadline && <span>Apply by {job.deadline}</span>}
      </div>
    </Link>
  );
}

export default async function JobsPage({ searchParams }: PageProps<"/jobs">) {
  const raw = await searchParams;
  const params = toSearchParams(raw);
  const [result, lookups] = await Promise.all([getJobs(params), getLookups()]);

  const jobs = result?.data ?? [];
  const meta = result?.meta;
  const countries = lookups?.countries ?? [];
  const categories = lookups?.job_categories ?? [];

  // Current filter values used to keep the form and pagination in sync.
  const current = {
    keyword: keywordFromRaw(raw) ?? "",
    country_id: firstValue(raw.country_id) ?? "",
    state_id: firstValue(raw.state_id) ?? "",
    district_id: firstValue(raw.district_id) ?? "",
    category: firstValue(raw.category) ?? "",
    experience: firstValue(raw.experience) ?? "",
    salary_min: firstValue(raw.salary_min) ?? "",
  };

  const currentPage = meta?.current_page ?? 1;
  const lastPage = meta?.last_page ?? 1;

  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <h1 className="text-2xl font-bold text-slate-900">Jobs</h1>
      <p className="mt-1 text-sm text-slate-600">
        {meta ? `${meta.total} job${meta.total === 1 ? "" : "s"} available` : "Browse live jobs"}
      </p>

      {/* Filters submit via GET so the URL carries the query (shareable + SSR-cached). */}
      <form method="get" action="/jobs" className="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-3">
        <div className="sm:col-span-2 lg:col-span-1">
          <label htmlFor="keyword" className="block text-sm font-medium text-slate-800">Keyword</label>
          <input
            id="keyword"
            name="keyword"
            defaultValue={current.keyword}
            placeholder="Job title or skill"
            className="mt-1 block w-full min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label htmlFor="country_id" className="block text-sm font-medium text-slate-800">Country</label>
          <select
            id="country_id"
            name="country_id"
            defaultValue={current.country_id}
            className="mt-1 block w-full min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
          >
            <option value="">All countries</option>
            {countries.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label htmlFor="category" className="block text-sm font-medium text-slate-800">Category / trade</label>
          <select
            id="category"
            name="category"
            defaultValue={current.category}
            className="mt-1 block w-full min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
          >
            <option value="">All trades</option>
            {categories.map((group) => (
              <optgroup key={group.id} label={group.name}>
                <option value={group.slug}>All {group.name.toLowerCase()} jobs</option>
                {group.trades.map((trade) => (
                  <option key={trade.id} value={trade.slug}>
                    {trade.name}
                  </option>
                ))}
              </optgroup>
            ))}
          </select>
        </div>

        <div>
          <label htmlFor="state_id" className="block text-sm font-medium text-slate-800">State / district ID</label>
          <input
            id="state_id"
            name="state_id"
            defaultValue={current.state_id}
            inputMode="numeric"
            placeholder="State ID (optional)"
            className="mt-1 block w-full min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label htmlFor="experience" className="block text-sm font-medium text-slate-800">Experience (years)</label>
          <input
            id="experience"
            name="experience"
            defaultValue={current.experience}
            inputMode="numeric"
            placeholder="e.g. 3"
            className="mt-1 block w-full min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label htmlFor="salary_min" className="block text-sm font-medium text-slate-800">Minimum salary</label>
          <input
            id="salary_min"
            name="salary_min"
            defaultValue={current.salary_min}
            inputMode="numeric"
            placeholder="e.g. 20000"
            className="mt-1 block w-full min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>

        <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
          <button type="submit" className={buttonClasses("primary")}>
            Search jobs
          </button>
          <Link href="/jobs" className={buttonClasses("secondary")}>
            Clear filters
          </Link>
        </div>
      </form>

      {/* Results */}
      <div className="mt-6">
        {jobs.length === 0 ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            No jobs match these filters. Try widening your search or clearing filters.
          </div>
        ) : (
          <div className="space-y-3">
            {jobs.map((job) => (
              <JobCard key={job.id} job={job} />
            ))}
          </div>
        )}
      </div>

      {/* Pagination as links that preserve the querystring */}
      {lastPage > 1 && (
        <nav className="mt-6 flex items-center justify-between" aria-label="Pagination">
          {currentPage > 1 ? (
            <Link href={pageHref(current, currentPage - 1)} className={buttonClasses("secondary")}>
              Previous
            </Link>
          ) : (
            <span className={`${buttonClasses("secondary")} pointer-events-none opacity-50`}>Previous</span>
          )}
          <span className="text-sm text-slate-600">
            Page {currentPage} of {lastPage}
          </span>
          {currentPage < lastPage ? (
            <Link href={pageHref(current, currentPage + 1)} className={buttonClasses("secondary")}>
              Next
            </Link>
          ) : (
            <span className={`${buttonClasses("secondary")} pointer-events-none opacity-50`}>Next</span>
          )}
        </nav>
      )}
    </div>
  );
}
