import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ApplyButton } from "@/components/public/ApplyButton";
import { getJob } from "@/lib/server-api";
import { APP_NAME, SITE_URL } from "@/lib/config";
import type { JobDetail } from "@/lib/types";

/** Trim a description into a plain-text meta description (~160 chars). */
function metaDescription(text: string): string {
  const plain = text.replace(/\s+/g, " ").trim();
  return plain.length > 160 ? `${plain.slice(0, 157)}…` : plain;
}

export async function generateMetadata({ params }: PageProps<"/jobs/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const job = await getJob(slug);

  if (!job) {
    return { title: `Job not found · ${APP_NAME}`, robots: { index: false, follow: false } };
  }

  const title = `${job.title} · ${APP_NAME}`;
  const description = metaDescription(job.description);
  const canonical = `${SITE_URL}/jobs/${slug}`;

  return {
    title,
    description,
    alternates: { canonical },
    openGraph: {
      title,
      description,
      url: canonical,
      type: "article",
      siteName: APP_NAME,
    },
  };
}

/** Builds a schema.org JobPosting, omitting null fields. */
function buildJsonLd(job: JobDetail): Record<string, unknown> {
  const jsonLd: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "JobPosting",
    title: job.title,
    description: job.description,
  };

  if (job.date_posted) jsonLd.datePosted = job.date_posted;
  if (job.valid_through) jsonLd.validThrough = job.valid_through;
  if (job.employment_type) jsonLd.employmentType = job.employment_type;

  const org = job.hiring_organization;
  if (org.name) {
    const organization: Record<string, unknown> = { "@type": "Organization", name: org.name };
    if (org.logo_url) organization.logo = org.logo_url;
    if (org.website) organization.sameAs = org.website;
    jsonLd.hiringOrganization = organization;
  }

  const loc = job.job_location;
  const address: Record<string, unknown> = { "@type": "PostalAddress" };
  if (loc.city) address.addressLocality = loc.city;
  if (loc.state) address.addressRegion = loc.state;
  if (loc.country) address.addressCountry = loc.country;
  if (Object.keys(address).length > 1) {
    jsonLd.jobLocation = { "@type": "Place", address };
  }

  const salary = job.base_salary;
  if (salary.min != null || salary.max != null) {
    // No salary-period field exists on the job, so `unitText` is omitted rather
    // than assuming a period (e.g. MONTH) that could mislabel annual/daily ranges.
    const value: Record<string, unknown> = { "@type": "QuantitativeValue" };
    if (salary.min != null) value.minValue = salary.min;
    if (salary.max != null) value.maxValue = salary.max;
    const monetary: Record<string, unknown> = { "@type": "MonetaryAmount", value };
    if (salary.currency) monetary.currency = salary.currency;
    jsonLd.baseSalary = monetary;
  }

  return jsonLd;
}

function locationString(job: JobDetail): string | null {
  const { city, district, state, country } = job.job_location;
  const parts = [city, district, state, country].filter((p): p is string => !!p && p.trim() !== "");
  return parts.length ? parts.join(", ") : null;
}

function salaryString(job: JobDetail): string | null {
  const { min, max, currency } = job.base_salary;
  if (min == null && max == null) return null;
  const cur = currency ?? "";
  const fmt = (n: number) => n.toLocaleString();
  if (min != null && max != null) return `${cur} ${fmt(min)} – ${fmt(max)}`.trim();
  if (min != null) return `${cur} ${fmt(min)}+`.trim();
  return `Up to ${cur} ${fmt(max as number)}`.trim();
}

function experienceString(job: JobDetail): string | null {
  const { experience_min, experience_max } = job;
  if (experience_min == null && experience_max == null) return null;
  if (experience_min != null && experience_max != null) return `${experience_min}–${experience_max} years`;
  if (experience_min != null) return `${experience_min}+ years`;
  return `Up to ${experience_max} years`;
}

export default async function JobDetailPage({ params }: PageProps<"/jobs/[slug]">) {
  const { slug } = await params;
  const job = await getJob(slug);

  if (!job) notFound();

  const jsonLd = buildJsonLd(job);
  const location = locationString(job);
  const salary = salaryString(job);
  const experience = experienceString(job);

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      {/* JobPosting structured data for search engines. */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }}
      />

      <nav className="mb-4 text-sm">
        <Link href="/jobs" className="font-semibold text-brand-700 hover:underline">
          ← Back to jobs
        </Link>
      </nav>

      <header className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div className="flex items-start justify-between gap-4">
          <div className="min-w-0">
            <h1 className="text-2xl font-bold text-slate-900">{job.title}</h1>
            <p className="mt-1 text-sm text-slate-600">
              {job.hiring_organization.name ?? "Confidential employer"}
              {location ? ` · ${location}` : ""}
            </p>
          </div>
          {job.category && (
            <span className="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
              {job.category}
            </span>
          )}
        </div>

        <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
          {salary && (
            <div>
              <dt className="text-slate-500">Salary</dt>
              <dd className="font-medium text-slate-800">{salary}</dd>
            </div>
          )}
          {experience && (
            <div>
              <dt className="text-slate-500">Experience</dt>
              <dd className="font-medium text-slate-800">{experience}</dd>
            </div>
          )}
          {job.education_level && (
            <div>
              <dt className="text-slate-500">Education</dt>
              <dd className="font-medium text-slate-800">{job.education_level}</dd>
            </div>
          )}
          {job.vacancies != null && (
            <div>
              <dt className="text-slate-500">Vacancies</dt>
              <dd className="font-medium text-slate-800">{job.vacancies}</dd>
            </div>
          )}
          {job.deadline && (
            <div>
              <dt className="text-slate-500">Apply by</dt>
              <dd className="font-medium text-slate-800">{job.deadline}</dd>
            </div>
          )}
          {job.hiring_organization.website && (
            <div>
              <dt className="text-slate-500">Website</dt>
              <dd className="truncate font-medium text-brand-700">
                <a href={job.hiring_organization.website} target="_blank" rel="noopener noreferrer" className="hover:underline">
                  {job.hiring_organization.website}
                </a>
              </dd>
            </div>
          )}
        </dl>
      </header>

      <section className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 className="text-lg font-semibold text-slate-900">Job description</h2>
        <div className="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">{job.description}</div>

        {job.skills.length > 0 && (
          <div className="mt-6">
            <h3 className="text-sm font-semibold text-slate-900">Skills</h3>
            <ul className="mt-2 flex flex-wrap gap-2">
              {job.skills.map((skill) => (
                <li
                  key={skill.id}
                  className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700"
                >
                  {skill.name}
                </li>
              ))}
            </ul>
          </div>
        )}
      </section>

      <section className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 className="text-lg font-semibold text-slate-900">Apply for this job</h2>
        <div className="mt-3">
          <ApplyButton slug={job.slug} title={job.title} />
        </div>
      </section>
    </div>
  );
}
