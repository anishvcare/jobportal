"use client";

import Link from "next/link";
import { useState } from "react";
import { AdminNav } from "@/components/admin/AdminNav";
import { JobStatusBadge } from "@/components/employer/StatusBadges";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { Field, Select, TextInput } from "@/components/ui/Field";
import { PageLoader } from "@/components/ui/Spinner";
import { closeAdminJob, hideAdminJob, unhideAdminJob, useAdminJobs } from "@/lib/admin";
import { errorMessage } from "@/lib/errors";
import type { AdminJob, JobStatus } from "@/lib/types";

const STATUS_FILTERS: { value: "" | JobStatus; label: string }[] = [
  { value: "", label: "All" },
  { value: "draft", label: "Draft" },
  { value: "published", label: "Published" },
  { value: "hidden", label: "Hidden" },
  { value: "expired", label: "Expired" },
  { value: "closed", label: "Closed" },
];

export default function AdminJobsPage() {
  const [status, setStatus] = useState<"" | JobStatus>("");
  const [keyword, setKeyword] = useState("");
  const [page, setPage] = useState(1);
  const { jobs, meta, isLoading, error, mutate } = useAdminJobs({
    status: status || undefined,
    keyword: keyword || undefined,
    page,
    per_page: 20,
  });
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  async function run(id: number, fn: (id: number) => Promise<unknown>) {
    setBusyId(id);
    setActionError(null);
    try {
      await fn(id);
      await mutate();
    } catch (err) {
      setActionError(errorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  const currentPage = meta?.current_page ?? page;
  const lastPage = meta?.last_page ?? 1;

  return (
    <>
      <AdminNav />
      <PageHeader title="Jobs" description="Moderate job postings across all employers." />

      <Card className="mb-6">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Keyword" htmlFor="keyword">
            <TextInput
              id="keyword"
              type="search"
              placeholder="Title or company"
              value={keyword}
              onChange={(e) => {
                setKeyword(e.target.value);
                setPage(1);
              }}
            />
          </Field>
          <Field label="Status" htmlFor="status">
            <Select
              id="status"
              value={status}
              onChange={(e) => {
                setStatus(e.target.value as "" | JobStatus);
                setPage(1);
              }}
            >
              {STATUS_FILTERS.map((f) => (
                <option key={f.value} value={f.value}>
                  {f.label}
                </option>
              ))}
            </Select>
          </Field>
        </div>
      </Card>

      {actionError && <div className="mb-4"><Alert tone="error">{actionError}</Alert></div>}

      {error ? (
        <Alert tone="error">{errorMessage(error)}</Alert>
      ) : isLoading && jobs.length === 0 ? (
        <PageLoader label="Loading jobs…" />
      ) : jobs.length === 0 ? (
        <Card>
          <p className="text-sm text-slate-600">No jobs match these filters.</p>
        </Card>
      ) : (
        <div className="space-y-3">
          {jobs.map((job: AdminJob) => (
            <Card key={job.id}>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <Link href={`/jobs/${job.slug}`} className="font-semibold text-brand-700 hover:underline">
                      {job.title}
                    </Link>
                    <JobStatusBadge status={job.status} />
                  </div>
                  <p className="mt-1 text-sm text-slate-600">
                    {job.employer?.company_name ?? "—"} · {job.category ?? "—"}
                    {job.city ? ` · ${job.city}` : ""}
                  </p>
                  <p className="mt-1 text-sm text-slate-500">
                    {job.application_count} applicant{job.application_count === 1 ? "" : "s"}
                    {job.deadline ? ` · deadline ${job.deadline}` : ""}
                  </p>
                </div>

                <div className="flex flex-wrap gap-2">
                  {job.is_hidden ? (
                    <Button variant="secondary" loading={busyId === job.id} onClick={() => run(job.id, unhideAdminJob)}>
                      Unhide
                    </Button>
                  ) : (
                    <Button variant="secondary" loading={busyId === job.id} onClick={() => run(job.id, hideAdminJob)}>
                      Hide
                    </Button>
                  )}
                  {job.status !== "closed" && (
                    <Button variant="danger" loading={busyId === job.id} onClick={() => run(job.id, closeAdminJob)}>
                      Close
                    </Button>
                  )}
                </div>
              </div>
            </Card>
          ))}

          <div className="mt-6 flex items-center justify-between">
            <button
              type="button"
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              disabled={currentPage <= 1}
              onClick={() => setPage(currentPage - 1)}
            >
              Previous
            </button>
            <span className="text-sm text-slate-600">
              Page {currentPage} of {lastPage}
            </span>
            <button
              type="button"
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              disabled={currentPage >= lastPage}
              onClick={() => setPage(currentPage + 1)}
            >
              Next
            </button>
          </div>
        </div>
      )}
    </>
  );
}
