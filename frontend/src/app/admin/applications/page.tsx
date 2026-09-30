"use client";

import Link from "next/link";
import { useState } from "react";
import { AdminNav } from "@/components/admin/AdminNav";
import { APPLICATION_STATUSES, ApplicationStatusBadge } from "@/components/employer/StatusBadges";
import { Alert } from "@/components/ui/Alert";
import { Card, PageHeader } from "@/components/ui/Card";
import { Field, Select } from "@/components/ui/Field";
import { PageLoader } from "@/components/ui/Spinner";
import { updateAdminApplicationStatus, useAdminApplications } from "@/lib/admin";
import { errorMessage } from "@/lib/errors";
import type { ApplicationStatus } from "@/lib/types";

function formatDate(value: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "—" : date.toLocaleDateString();
}

export default function AdminApplicationsPage() {
  const [status, setStatus] = useState<"" | ApplicationStatus>("");
  const [page, setPage] = useState(1);
  const { applications, meta, isLoading, error, mutate } = useAdminApplications({
    status: status || undefined,
    page,
    per_page: 20,
  });
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  async function onStatusChange(id: number, next: ApplicationStatus) {
    setBusyId(id);
    setActionError(null);
    try {
      await updateAdminApplicationStatus(id, next);
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
      <PageHeader title="Applications" description="Review applications and update their status." />

      <Card className="mb-6">
        <Field label="Status" htmlFor="status">
          <Select
            id="status"
            className="w-auto"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value as "" | ApplicationStatus);
              setPage(1);
            }}
          >
            <option value="">All</option>
            {APPLICATION_STATUSES.map((s) => (
              <option key={s} value={s}>
                {s.charAt(0).toUpperCase() + s.slice(1)}
              </option>
            ))}
          </Select>
        </Field>
      </Card>

      {actionError && <div className="mb-4"><Alert tone="error">{actionError}</Alert></div>}

      {error ? (
        <Alert tone="error">{errorMessage(error)}</Alert>
      ) : isLoading && applications.length === 0 ? (
        <PageLoader label="Loading applications…" />
      ) : applications.length === 0 ? (
        <Card>
          <p className="text-sm text-slate-600">No applications match this filter.</p>
        </Card>
      ) : (
        <div className="space-y-3">
          {applications.map((application) => (
            <Card key={application.id}>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    {application.job ? (
                      <Link
                        href={`/jobs/${application.job.slug}`}
                        className="font-semibold text-brand-700 hover:underline"
                      >
                        {application.job.title}
                      </Link>
                    ) : (
                      <span className="font-semibold text-slate-500">Job removed</span>
                    )}
                    <ApplicationStatusBadge status={application.status} />
                  </div>
                  <p className="mt-1 text-sm text-slate-600">
                    {application.candidate?.full_name ?? "(no name)"}
                    {application.candidate?.user ? ` · ${application.candidate.user.email}` : ""}
                  </p>
                  <p className="mt-1 text-sm text-slate-500">Applied {formatDate(application.created_at)}</p>
                </div>

                <Field label="Update status" htmlFor={`status-${application.id}`}>
                  <Select
                    id={`status-${application.id}`}
                    className="w-auto"
                    value={application.status}
                    disabled={busyId === application.id}
                    onChange={(e) => onStatusChange(application.id, e.target.value as ApplicationStatus)}
                  >
                    {APPLICATION_STATUSES.map((s) => (
                      <option key={s} value={s}>
                        {s.charAt(0).toUpperCase() + s.slice(1)}
                      </option>
                    ))}
                  </Select>
                </Field>
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
