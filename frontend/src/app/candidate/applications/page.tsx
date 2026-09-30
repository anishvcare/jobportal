"use client";

import Link from "next/link";
import { useState } from "react";
import { ApplicationStatusBadge } from "@/components/employer/StatusBadges";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { ErrorState } from "@/components/ui/ErrorState";
import { PageLoader } from "@/components/ui/Spinner";
import { useMyApplications, withdrawApplication } from "@/lib/candidate";
import { errorMessage } from "@/lib/errors";

function formatDate(value: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "—" : date.toLocaleDateString();
}

export default function MyApplicationsPage() {
  const { applications, isLoading, error, mutate } = useMyApplications();
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  async function onWithdraw(id: number) {
    setBusyId(id);
    setActionError(null);
    try {
      await withdrawApplication(id);
      await mutate();
    } catch (err) {
      setActionError(errorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <>
      <PageHeader
        title="My applications"
        description="Track the status of every job you've applied to."
        actions={<ButtonLink href="/jobs" variant="secondary">Browse jobs</ButtonLink>}
      />

      {actionError && <div className="mb-4"><Alert tone="error">{actionError}</Alert></div>}

      {error ? (
        <ErrorState error={error} onRetry={() => void mutate()} />
      ) : isLoading && applications.length === 0 ? (
        <PageLoader label="Loading your applications…" />
      ) : applications.length === 0 ? (
        <Card>
          <p className="text-sm text-slate-600">
            You haven&apos;t applied to any jobs yet.{" "}
            <Link href="/jobs" className="font-semibold text-brand-700 hover:underline">
              Find a job to apply
            </Link>
            .
          </p>
        </Card>
      ) : (
        <div className="space-y-3">
          {applications.map((application) => {
            const job = application.job;
            const canWithdraw = job !== null && job.closed === false;
            return (
              <Card key={application.id}>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      {job ? (
                        <Link href={`/jobs/${job.slug}`} className="font-semibold text-brand-700 hover:underline">
                          {job.title}
                        </Link>
                      ) : (
                        <span className="font-semibold text-slate-500">Job no longer available</span>
                      )}
                      <ApplicationStatusBadge status={application.status} />
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                      {job?.company_name ?? "—"} · Applied {formatDate(application.created_at)}
                    </p>
                  </div>

                  {canWithdraw && (
                    <Button
                      variant="ghost"
                      loading={busyId === application.id}
                      onClick={() => onWithdraw(application.id)}
                    >
                      Withdraw
                    </Button>
                  )}
                </div>
              </Card>
            );
          })}
        </div>
      )}
    </>
  );
}
