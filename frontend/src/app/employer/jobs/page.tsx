"use client";

import Link from "next/link";
import { useState } from "react";
import { EmployerNav } from "@/components/employer/EmployerNav";
import { JobStatusBadge } from "@/components/employer/StatusBadges";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { ErrorState } from "@/components/ui/ErrorState";
import { PageLoader } from "@/components/ui/Spinner";
import { closeJob, publishJob, useEmployerJobs, useEmployerProfile } from "@/lib/employer";
import { errorMessage } from "@/lib/errors";
import type { EmployerJob } from "@/lib/types";

export default function EmployerJobsPage() {
  const { profile } = useEmployerProfile();
  const { jobs, isLoading, error, mutate } = useEmployerJobs();
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const approved = profile?.status === "approved";

  async function onPublish(job: EmployerJob) {
    setBusyId(job.id);
    setActionError(null);
    try {
      await publishJob(job.id);
      await mutate();
    } catch (err) {
      setActionError(errorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  async function onClose(job: EmployerJob) {
    setBusyId(job.id);
    setActionError(null);
    try {
      await closeJob(job.id);
      await mutate();
    } catch (err) {
      setActionError(errorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <>
      <EmployerNav />
      <PageHeader
        title="Your jobs"
        description="Create, publish and close job postings."
        actions={<ButtonLink href="/employer/jobs/new">Post a job</ButtonLink>}
      />

      {!approved && profile && (
        <div className="mb-4">
          <Alert tone={profile.status === "suspended" ? "warning" : "info"}>
            {profile.status === "suspended"
              ? "Your account is suspended, so you cannot publish jobs right now."
              : "Your account is awaiting approval. You can create and edit drafts now, but publishing is disabled until an admin approves your company."}
          </Alert>
        </div>
      )}

      {actionError && <div className="mb-4"><Alert tone="error">{actionError}</Alert></div>}

      {error ? (
        <ErrorState error={error} onRetry={() => void mutate()} />
      ) : isLoading && jobs.length === 0 ? (
        <PageLoader label="Loading your jobs…" />
      ) : jobs.length === 0 ? (
        <Card>
          <p className="text-sm text-slate-600">
            You haven&apos;t posted any jobs yet.{" "}
            <Link href="/employer/jobs/new" className="font-semibold text-brand-700 hover:underline">
              Post your first job
            </Link>
            .
          </p>
        </Card>
      ) : (
        <div className="space-y-3">
          {jobs.map((job) => {
            const canPublish = approved && (job.status === "draft" || job.status === "hidden");
            const canClose = job.status !== "closed";
            return (
              <Card key={job.id}>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-semibold text-slate-900">{job.title}</h2>
                      <JobStatusBadge status={job.status} />
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                      {job.category ?? "—"}
                      {job.city ? ` · ${job.city}` : ""} · {job.vacancies ?? 1} vacancy
                      {(job.vacancies ?? 1) === 1 ? "" : "ies"}
                    </p>
                    <p className="mt-1 text-sm text-slate-500">
                      <Link
                        href={`/employer/jobs/${job.id}/applicants`}
                        className="font-medium text-brand-700 hover:underline"
                      >
                        {job.application_count} applicant{job.application_count === 1 ? "" : "s"}
                      </Link>
                      {job.deadline ? ` · deadline ${job.deadline}` : ""}
                    </p>
                  </div>

                  <div className="flex flex-wrap gap-2">
                    <ButtonLink href={`/employer/jobs/${job.id}/edit`} variant="secondary">
                      Edit
                    </ButtonLink>
                    {job.status !== "published" && (
                      <Button
                        variant="primary"
                        disabled={!canPublish}
                        loading={busyId === job.id}
                        onClick={() => onPublish(job)}
                        title={!approved ? "Your company must be approved before you can publish." : undefined}
                      >
                        Publish
                      </Button>
                    )}
                    {canClose && (
                      <Button variant="danger" loading={busyId === job.id} onClick={() => onClose(job)}>
                        Close
                      </Button>
                    )}
                  </div>
                </div>
              </Card>
            );
          })}
        </div>
      )}
    </>
  );
}
