"use client";

import { use, useState } from "react";
import { AdminPhoto } from "@/components/admin/AdminPhoto";
import { EmployerNav } from "@/components/employer/EmployerNav";
import { ApplicationStatusBadge } from "@/components/employer/StatusBadges";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { ErrorState } from "@/components/ui/ErrorState";
import { Select } from "@/components/ui/Field";
import { PageLoader } from "@/components/ui/Spinner";
import {
  downloadApplicantResume,
  updateApplicantStatus,
  useEmployerJob,
  useJobApplicants,
} from "@/lib/employer";
import { errorMessage } from "@/lib/errors";
import type { ApplicationStatus } from "@/lib/types";

const STATUS_OPTIONS: { value: ApplicationStatus; label: string }[] = [
  { value: "applied", label: "Applied" },
  { value: "shortlisted", label: "Shortlisted" },
  { value: "rejected", label: "Rejected" },
  { value: "selected", label: "Selected" },
];

export default function JobApplicantsPage({ params }: PageProps<"/employer/jobs/[id]/applicants">) {
  const { id } = use(params);
  const jobId = Number(id);
  const validId = Number.isFinite(jobId) ? jobId : null;

  const { job } = useEmployerJob(validId);
  const { applicants, isLoading, error, mutate } = useJobApplicants(validId);
  const [actionError, setActionError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  async function onStatusChange(applicationId: number, status: ApplicationStatus) {
    setBusyId(applicationId);
    setActionError(null);
    try {
      await updateApplicantStatus(applicationId, status);
      await mutate();
    } catch (err) {
      setActionError(errorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  async function onDownload(applicationId: number) {
    setBusyId(applicationId);
    setActionError(null);
    try {
      await downloadApplicantResume(applicationId);
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
        title="Applicants"
        description={job ? job.title : "Review applicants and update their status."}
        actions={<ButtonLink href="/employer/jobs" variant="secondary">Back to jobs</ButtonLink>}
      />

      {actionError && <div className="mb-4"><Alert tone="error">{actionError}</Alert></div>}

      {error ? (
        <ErrorState error={error} onRetry={() => void mutate()} />
      ) : isLoading && applicants.length === 0 ? (
        <PageLoader label="Loading applicants…" />
      ) : applicants.length === 0 ? (
        <Card>
          <p className="text-sm text-slate-600">No one has applied to this job yet.</p>
        </Card>
      ) : (
        <div className="space-y-3">
          {applicants.map((applicant) => (
            <Card key={applicant.id}>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex items-start gap-3">
                  <AdminPhoto photoUrl={applicant.candidate.photo_url} alt="" className="h-14 w-14 shrink-0 rounded-full" />
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="font-semibold text-slate-900">{applicant.candidate.full_name ?? "(no name)"}</p>
                      <ApplicationStatusBadge status={applicant.status} />
                    </div>
                    <p className="mt-0.5 text-sm text-slate-600">
                      {applicant.candidate.age != null ? `${applicant.candidate.age} yrs · ` : ""}
                      {applicant.candidate.district ?? "—"}
                      {applicant.candidate.phone ? ` · ${applicant.candidate.phone}` : ""}
                    </p>
                    {applicant.candidate.trades && applicant.candidate.trades.length > 0 && (
                      <p className="mt-0.5 text-sm text-slate-600">{applicant.candidate.trades.join(", ")}</p>
                    )}
                    {applicant.cover_note && (
                      <p className="mt-2 rounded-lg bg-slate-50 p-2 text-sm text-slate-700">{applicant.cover_note}</p>
                    )}
                  </div>
                </div>

                <div className="flex flex-col gap-2 sm:items-end">
                  <Select
                    aria-label={`Status for ${applicant.candidate.full_name ?? "applicant"}`}
                    className="w-auto"
                    value={applicant.status}
                    disabled={busyId === applicant.id}
                    onChange={(e) => onStatusChange(applicant.id, e.target.value as ApplicationStatus)}
                  >
                    {STATUS_OPTIONS.map((s) => (
                      <option key={s.value} value={s.value}>
                        {s.label}
                      </option>
                    ))}
                  </Select>
                  <Button
                    variant="secondary"
                    loading={busyId === applicant.id}
                    onClick={() => onDownload(applicant.id)}
                  >
                    Download résumé
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}
