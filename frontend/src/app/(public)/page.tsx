import Link from "next/link";
import { getJobs, getLookups } from "@/lib/server-api";
import { buttonClasses } from "@/components/ui/Button";
import { InstallPrompt } from "@/components/pwa/InstallPrompt";

export const revalidate = 300;

export default async function HomePage() {
  const [lookups, latest] = await Promise.all([getLookups(), getJobs({ per_page: 6 })]);
  const categories = lookups?.job_categories ?? [];
  const latestJobs = latest?.data ?? [];

  return (
    <>
      <section className="bg-gradient-to-b from-brand-700 to-brand-800 text-white">
        <div className="mx-auto max-w-6xl px-4 py-10 sm:py-16">
          <h1 className="max-w-2xl text-3xl font-bold leading-tight sm:text-4xl">
            Find your next job in Kerala, India, Russia and beyond
          </h1>
          <p className="mt-3 max-w-xl text-brand-100">
            Build your profile once, upload your documents from your phone and apply in minutes.
          </p>

          <form action="/jobs" method="get" role="search" className="mt-6 grid gap-2 rounded-xl bg-white p-3 text-slate-900 shadow-lg sm:grid-cols-[1fr_1fr_1fr_auto]">
            <label className="sr-only" htmlFor="q">Keyword</label>
            <input id="q" name="q" placeholder="Job title or skill" className="min-h-11 rounded-lg border border-slate-300 px-3" />
            <label className="sr-only" htmlFor="location">Location</label>
            <input id="location" name="location" placeholder="City, state or country" className="min-h-11 rounded-lg border border-slate-300 px-3" />
            <label className="sr-only" htmlFor="category">Category</label>
            <select id="category" name="category" defaultValue="" className="min-h-11 rounded-lg border border-slate-300 bg-white px-3">
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
            <button type="submit" className={buttonClasses("primary")}>
              Search jobs
            </button>
          </form>
        </div>
      </section>

      <div className="mx-auto max-w-6xl px-4 empty:hidden [&:has(aside)]:pt-6">
        <InstallPrompt />
      </div>

      <section className="mx-auto grid max-w-6xl gap-4 px-4 py-8 sm:grid-cols-2">
        <div className="rounded-xl border border-slate-200 bg-white p-6">
          <h2 className="text-xl font-semibold">I&apos;m a candidate</h2>
          <p className="mt-2 text-sm text-slate-600">
            Create your profile, upload your passport, Aadhaar and certificates, get a professional resume and apply to jobs.
          </p>
          <Link href="/auth/login?as=candidate" className={buttonClasses("primary", "mt-4")}>
            Create my profile
          </Link>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-6">
          <h2 className="text-xl font-semibold">I&apos;m an employer</h2>
          <p className="mt-2 text-sm text-slate-600">
            Register your company, post jobs once approved and manage applicants in one place.
          </p>
          <Link href="/auth/login?as=employer" className={buttonClasses("secondary", "mt-4")}>
            Post a job
          </Link>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-10">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold">Latest jobs</h2>
          <Link href="/jobs" className="text-sm font-semibold text-brand-700 hover:underline">
            View all
          </Link>
        </div>
        {latestJobs.length === 0 ? (
          <p className="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
            New jobs will appear here soon.
          </p>
        ) : (
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {latestJobs.map((job) => (
              <Link
                key={job.id}
                href={`/jobs/${job.slug}`}
                className="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-colors hover:border-brand-300 hover:bg-brand-50/40"
              >
                <h3 className="truncate font-semibold text-slate-900">{job.title}</h3>
                <p className="mt-0.5 text-sm text-slate-600">
                  {job.company_name ?? "Confidential employer"}
                  {job.location ? ` · ${job.location}` : ""}
                </p>
                {job.category && <p className="mt-2 text-xs font-medium text-slate-500">{job.category}</p>}
              </Link>
            ))}
          </div>
        )}
      </section>
    </>
  );
}
