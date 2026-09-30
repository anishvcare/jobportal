"use client";

import { useParams } from "next/navigation";
import { useState } from "react";
import { AdminDownloads } from "@/components/admin/AdminDownloads";
import { AdminNav } from "@/components/admin/AdminNav";
import { AdminPhoto } from "@/components/admin/AdminPhoto";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { ErrorState } from "@/components/ui/ErrorState";
import { PageLoader } from "@/components/ui/Spinner";
import { downloadAdminDocument, useCandidateDetail } from "@/lib/admin";
import { errorMessage, httpStatus } from "@/lib/errors";
import type { AdminDocument } from "@/lib/types";

function Row({ label, value }: { label: string; value: string | number | null | undefined }) {
  return (
    <div className="flex flex-col gap-0.5 border-b border-slate-100 py-2 sm:flex-row sm:gap-4">
      <dt className="w-48 shrink-0 text-sm font-medium text-slate-500">{label}</dt>
      <dd className="text-sm text-slate-900">{value === null || value === undefined || value === "" ? "—" : value}</dd>
    </div>
  );
}

function formatBytes(size: number): string {
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(0)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function DocumentRow({ profileId, doc }: { profileId: number; doc: AdminDocument }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function download() {
    if (busy) return;
    setError(null);
    setBusy(true);
    try {
      await downloadAdminDocument(profileId, doc.id, doc.original_name);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <li className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="min-w-0">
        <p className="truncate text-sm font-medium text-slate-900">{doc.original_name}</p>
        <p className="text-xs text-slate-500">
          {doc.type.replace(/_/g, " ")} · {formatBytes(doc.size)}
          {doc.page_count ? ` · ${doc.page_count} pages` : ""}
        </p>
        {error && <p className="mt-1 text-xs text-red-700">{error}</p>}
      </div>
      <Button type="button" variant="secondary" loading={busy} disabled={busy} onClick={() => void download()}>
        Download
      </Button>
    </li>
  );
}

export default function AdminCandidateDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const validId = Number.isFinite(id) && id > 0 ? id : null;

  const { candidate, isLoading, error, mutate } = useCandidateDetail(validId);

  const backLink = (
    <ButtonLink href="/admin/candidates" variant="ghost">
      ← Back to search
    </ButtonLink>
  );

  if (error) {
    const status = httpStatus(error);
    return (
      <>
        <AdminNav />
        <div className="mb-4">{backLink}</div>
        {status === 404 ? (
          <Alert tone="error">That candidate could not be found.</Alert>
        ) : (
          <ErrorState error={error} onRetry={() => void mutate()} />
        )}
      </>
    );
  }

  if (isLoading || !candidate) {
    return (
      <>
        <AdminNav />
        <PageLoader label="Loading candidate…" />
      </>
    );
  }

  return (
    <>
      <AdminNav />
      <PageHeader title={candidate.full_name ?? "Candidate"} description={candidate.user?.email ?? undefined} actions={backLink} />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <div className="flex flex-col gap-4 sm:flex-row">
              <AdminPhoto photoUrl={candidate.photo_url} alt={candidate.full_name ?? "Candidate photo"} className="h-28 w-28 rounded-xl" />
              <dl className="min-w-0 flex-1">
                <Row label="Full name" value={candidate.full_name} />
                <Row label="Age" value={candidate.age !== null ? `${candidate.age}` : null} />
                <Row label="Date of birth" value={candidate.dob} />
                <Row label="Gender" value={candidate.gender} />
                <Row label="Phone" value={candidate.phone} />
                <Row label="WhatsApp" value={candidate.whatsapp} />
                <Row label="Completeness" value={`${candidate.completeness.percentage}%`} />
              </dl>
            </div>
          </Card>

          <Card>
            <h2 className="mb-2 font-semibold text-slate-900">Location</h2>
            <dl>
              <Row label="Address" value={candidate.address} />
              <Row label="City" value={candidate.city} />
              <Row label="District" value={candidate.district?.name} />
              <Row label="State" value={candidate.state?.name} />
              <Row label="Country" value={candidate.country?.name} />
              <Row label="Pincode" value={candidate.pincode} />
            </dl>
          </Card>

          <Card>
            <h2 className="mb-2 font-semibold text-slate-900">Passport</h2>
            <dl>
              <Row label="Has passport" value={candidate.has_passport ? "Yes" : "No"} />
              <Row label="Passport number" value={candidate.passport_number} />
              <Row label="Expiry" value={candidate.passport_expiry} />
              <Row label="Valid" value={candidate.passport_valid ? "Yes" : "No"} />
            </dl>
          </Card>

          {candidate.summary && (
            <Card>
              <h2 className="mb-2 font-semibold text-slate-900">Summary</h2>
              <p className="whitespace-pre-line text-sm text-slate-700">{candidate.summary}</p>
            </Card>
          )}

          <Card>
            <h2 className="mb-2 font-semibold text-slate-900">Education</h2>
            {candidate.educations.length === 0 ? (
              <p className="text-sm text-slate-500">No education recorded.</p>
            ) : (
              <ul className="space-y-2">
                {candidate.educations.map((e) => (
                  <li key={e.id} className="text-sm text-slate-700">
                    <span className="font-medium text-slate-900">{e.education_level?.name ?? "Education"}</span>
                    {e.field_of_study ? ` · ${e.field_of_study}` : ""}
                    {e.institution ? ` · ${e.institution}` : ""}
                    {e.year_completed ? ` (${e.year_completed})` : ""}
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card>
            <h2 className="mb-2 font-semibold text-slate-900">Experience</h2>
            {candidate.experiences.length === 0 ? (
              <p className="text-sm text-slate-500">No experience recorded.</p>
            ) : (
              <ul className="space-y-2">
                {candidate.experiences.map((x) => (
                  <li key={x.id} className="text-sm text-slate-700">
                    <span className="font-medium text-slate-900">{x.job_title}</span>
                    {x.company ? ` · ${x.company}` : ""}
                    {x.job_category ? ` · ${x.job_category.name}` : ""}
                    <span className="text-slate-500">
                      {" "}
                      ({x.start_date ?? "?"} – {x.is_current ? "present" : (x.end_date ?? "?")})
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <h2 className="mb-3 font-semibold text-slate-900">Downloads</h2>
            <AdminDownloads profileId={candidate.id} />
          </Card>

          <Card>
            <h2 className="mb-2 font-semibold text-slate-900">Documents</h2>
            {candidate.documents.length === 0 ? (
              <p className="text-sm text-slate-500">No documents uploaded.</p>
            ) : (
              <ul className="divide-y divide-slate-100">
                {candidate.documents.map((doc) => (
                  <DocumentRow key={doc.id} profileId={candidate.id} doc={doc} />
                ))}
              </ul>
            )}
          </Card>

          <Card>
            <h2 className="mb-2 font-semibold text-slate-900">Skills</h2>
            {candidate.skills.length === 0 ? (
              <p className="text-sm text-slate-500">None</p>
            ) : (
              <div className="flex flex-wrap gap-2">
                {candidate.skills.map((s) => (
                  <span key={s.id} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                    {s.name}
                  </span>
                ))}
              </div>
            )}
          </Card>

          <Card>
            <h2 className="mb-2 font-semibold text-slate-900">Languages</h2>
            {candidate.languages.length === 0 ? (
              <p className="text-sm text-slate-500">None</p>
            ) : (
              <ul className="space-y-1 text-sm text-slate-700">
                {candidate.languages.map((l) => (
                  <li key={l.id}>
                    {l.name}
                    {l.proficiency ? <span className="text-slate-500"> · {l.proficiency}</span> : null}
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card>
            <h2 className="mb-2 font-semibold text-slate-900">Preferences</h2>
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Trades</p>
            <p className="mb-2 text-sm text-slate-700">
              {candidate.preferred_categories.length
                ? candidate.preferred_categories.map((c) => c.name).join(", ")
                : "—"}
            </p>
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Countries</p>
            <p className="text-sm text-slate-700">
              {candidate.preferred_countries.length
                ? candidate.preferred_countries.map((c) => c.name).join(", ")
                : "—"}
            </p>
          </Card>
        </div>
      </div>
    </>
  );
}
