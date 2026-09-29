"use client";

import { DocumentUploader } from "./DocumentUploader";
import type { DocumentDto, DocumentType } from "@/lib/types";

/**
 * A document type that can hold many files (education / skill / experience certs).
 * Existing files each get their own replace/delete controls; a trailing uploader
 * appends a new one.
 */
export function MultiDocumentSection({
  type,
  documents,
  addLabel,
  onChanged,
}: {
  type: DocumentType;
  documents: DocumentDto[];
  addLabel: string;
  onChanged?: () => void;
}) {
  const ordered = [...documents].sort(
    (a, b) => a.sort_order - b.sort_order || a.id - b.id,
  );

  return (
    <div className="space-y-4">
      {ordered.length > 0 && (
        <ul className="space-y-3">
          {ordered.map((doc) => (
            <li key={doc.id} className="rounded-lg border border-slate-200 p-3">
              <DocumentUploader type={type} document={doc} onChanged={onChanged} />
            </li>
          ))}
        </ul>
      )}

      <DocumentUploader type={type} cameraCapture addLabel={addLabel} onChanged={onChanged} />
    </div>
  );
}
