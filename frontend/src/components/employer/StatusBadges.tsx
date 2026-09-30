import type { ApplicationStatus, EmployerStatus, JobStatus } from "@/lib/types";

const JOB_TONES: Record<JobStatus, string> = {
  draft: "bg-slate-100 text-slate-700",
  published: "bg-emerald-100 text-emerald-800",
  hidden: "bg-amber-100 text-amber-800",
  expired: "bg-orange-100 text-orange-800",
  closed: "bg-red-100 text-red-800",
};

/** Coloured badge for a job posting's lifecycle status. */
export function JobStatusBadge({ status }: { status: JobStatus }) {
  return (
    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium capitalize ${JOB_TONES[status]}`}>
      {status}
    </span>
  );
}

const APPLICATION_TONES: Record<ApplicationStatus, string> = {
  applied: "bg-sky-100 text-sky-800",
  shortlisted: "bg-amber-100 text-amber-800",
  rejected: "bg-red-100 text-red-800",
  selected: "bg-emerald-100 text-emerald-800",
};

/** Coloured badge for an application status. */
export function ApplicationStatusBadge({ status }: { status: ApplicationStatus }) {
  return (
    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium capitalize ${APPLICATION_TONES[status]}`}>
      {status}
    </span>
  );
}

const EMPLOYER_TONES: Record<EmployerStatus, string> = {
  pending: "bg-amber-100 text-amber-800",
  approved: "bg-emerald-100 text-emerald-800",
  suspended: "bg-red-100 text-red-800",
};

/** Coloured badge for an employer's approval status. */
export function EmployerStatusBadge({ status }: { status: EmployerStatus }) {
  return (
    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium capitalize ${EMPLOYER_TONES[status]}`}>
      {status}
    </span>
  );
}

export const APPLICATION_STATUSES: ApplicationStatus[] = ["applied", "shortlisted", "rejected", "selected"];
