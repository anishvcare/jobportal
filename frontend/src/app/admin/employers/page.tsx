"use client";

import { useState } from "react";
import { AdminNav } from "@/components/admin/AdminNav";
import { EmployerStatusBadge } from "@/components/employer/StatusBadges";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { Field, Select } from "@/components/ui/Field";
import { PageLoader } from "@/components/ui/Spinner";
import { approveEmployer, suspendEmployer, useAdminEmployers } from "@/lib/admin";
import { errorMessage } from "@/lib/errors";
import type { EmployerStatus } from "@/lib/types";

const STATUS_FILTERS: { value: "" | EmployerStatus; label: string }[] = [
  { value: "", label: "All" },
  { value: "pending", label: "Pending" },
  { value: "approved", label: "Approved" },
  { value: "suspended", label: "Suspended" },
];

export default function AdminEmployersPage() {
  const [status, setStatus] = useState<"" | EmployerStatus>("");
  const [page, setPage] = useState(1);
  const { employers, meta, isLoading, error, mutate } = useAdminEmployers({
    status: status || undefined,
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
      <PageHeader title="Employers" description="Approve or suspend employer accounts." />

      <Card className="mb-6">
        <Field label="Status" htmlFor="status">
          <Select
            id="status"
            className="w-auto"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value as "" | EmployerStatus);
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
      </Card>

      {actionError && <div className="mb-4"><Alert tone="error">{actionError}</Alert></div>}

      {error ? (
        <Alert tone="error">{errorMessage(error)}</Alert>
      ) : isLoading && employers.length === 0 ? (
        <PageLoader label="Loading employers…" />
      ) : employers.length === 0 ? (
        <Card>
          <p className="text-sm text-slate-600">No employers match this filter.</p>
        </Card>
      ) : (
        <div className="space-y-3">
          {employers.map((employer) => (
            <Card key={employer.id}>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-semibold text-slate-900">{employer.company_name ?? "(no name)"}</h2>
                    <EmployerStatusBadge status={employer.status} />
                  </div>
                  <p className="mt-1 text-sm text-slate-600">
                    {employer.contact_person ?? "—"}
                    {employer.user ? ` · ${employer.user.email}` : ""}
                  </p>
                  <p className="mt-1 text-sm text-slate-500">
                    {employer.city ?? employer.country ?? "—"} · {employer.job_count} job
                    {employer.job_count === 1 ? "" : "s"}
                  </p>
                </div>

                <div className="flex flex-wrap gap-2">
                  <Button
                    variant="primary"
                    disabled={employer.status === "approved" || busyId === employer.id}
                    loading={busyId === employer.id}
                    onClick={() => run(employer.id, approveEmployer)}
                  >
                    Approve
                  </Button>
                  <Button
                    variant="danger"
                    disabled={employer.status === "suspended" || busyId === employer.id}
                    loading={busyId === employer.id}
                    onClick={() => run(employer.id, suspendEmployer)}
                  >
                    Suspend
                  </Button>
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
