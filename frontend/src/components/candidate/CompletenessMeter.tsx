import Link from "next/link";
import type { Completeness, DocumentType } from "@/lib/types";

/** Human labels for each required document type. */
const DOCUMENT_LABELS: Record<string, string> = {
  photo: "Passport-size photo",
  aadhaar_front: "Aadhaar card (front)",
  aadhaar_back: "Aadhaar card (back)",
  sslc: "SSLC book",
  passport: "Passport pages",
  education_cert: "Education certificate",
  skill_cert: "Skill certificate",
  experience_cert: "Experience certificate",
  cv: "CV",
  profile_pdf: "Profile PDF",
};

export function documentLabel(type: DocumentType | string): string {
  return DOCUMENT_LABELS[type] ?? type;
}

export function CompletenessMeter({
  completeness,
  documentsHref = "/candidate/documents",
  className = "",
}: {
  completeness: Completeness;
  documentsHref?: string;
  className?: string;
}) {
  const { percentage, missing } = completeness;
  const complete = missing.length === 0;

  return (
    <div className={className}>
      <div className="flex items-center justify-between text-sm">
        <span className="font-medium text-slate-800">Profile completeness</span>
        <span className="font-semibold text-brand-700">{percentage}%</span>
      </div>
      <div
        className="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-200"
        role="progressbar"
        aria-valuenow={percentage}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label="Profile completeness"
      >
        <div
          className={`h-full rounded-full transition-all ${complete ? "bg-emerald-500" : "bg-brand-600"}`}
          style={{ width: `${Math.min(100, Math.max(0, percentage))}%` }}
        />
      </div>

      {complete ? (
        <p className="mt-3 text-sm text-emerald-700">All required documents are uploaded.</p>
      ) : (
        <div className="mt-3">
          <p className="text-sm text-slate-600">Still needed:</p>
          <ul className="mt-1 space-y-1">
            {missing.map((type) => (
              <li key={type} className="flex items-center gap-2 text-sm text-slate-700">
                <span aria-hidden className="inline-block h-1.5 w-1.5 rounded-full bg-amber-500" />
                {documentLabel(type)}
              </li>
            ))}
          </ul>
          <Link
            href={documentsHref}
            className="mt-3 inline-block text-sm font-semibold text-brand-700 hover:text-brand-800"
          >
            Upload documents &rarr;
          </Link>
        </div>
      )}
    </div>
  );
}
