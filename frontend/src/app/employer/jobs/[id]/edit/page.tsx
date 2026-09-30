"use client";

import { use } from "react";
import { EmployerNav } from "@/components/employer/EmployerNav";
import { JobForm } from "@/components/employer/JobForm";
import { Alert } from "@/components/ui/Alert";
import { PageHeader } from "@/components/ui/Card";
import { PageLoader } from "@/components/ui/Spinner";
import { useEmployerJob } from "@/lib/employer";
import { errorMessage } from "@/lib/errors";

export default function EditJobPage({ params }: PageProps<"/employer/jobs/[id]/edit">) {
  const { id } = use(params);
  const jobId = Number(id);
  const { job, isLoading, error } = useEmployerJob(Number.isFinite(jobId) ? jobId : null);

  return (
    <>
      <EmployerNav />
      <PageHeader title="Edit job" description="Update the details of this job posting." />
      {error ? (
        <Alert tone="error">{errorMessage(error)}</Alert>
      ) : isLoading || !job ? (
        <PageLoader label="Loading job…" />
      ) : (
        <JobForm key={job.id} job={job} />
      )}
    </>
  );
}
