"use client";

import { useEffect, useRef, useState } from "react";
import { documentLabel } from "@/components/candidate/CompletenessMeter";
import { Button } from "@/components/ui/Button";
import { Alert } from "@/components/ui/Alert";
import { Progress } from "@/components/ui/Progress";
import { errorMessage } from "@/lib/errors";
import type { DocumentDto, DocumentType } from "@/lib/types";
import {
  UploadPrepError,
  assertWithinSizeLimit,
  deleteDocument,
  fetchDocumentObjectUrl,
  prepareImage,
  replaceDocument,
  uploadDocument,
} from "@/lib/uploads";

const IMAGE_ACCEPT = "image/jpeg,image/png,image/heic,image/heif";
const PDF_ACCEPT = "application/pdf";

interface DocumentUploaderProps {
  type: DocumentType;
  /** When true, the file picker only accepts PDFs (profile_pdf). */
  pdfOnly?: boolean;
  /** Enables phone-camera capture on the image picker. */
  cameraCapture?: boolean;
  /** An existing document of this type to preview / replace / delete. */
  document?: DocumentDto;
  /** Optional label for the add/replace button. */
  addLabel?: string;
  /** Called after a successful upload/replace/delete so the parent can react. */
  onChanged?: () => void;
}

/** Preview thumbnail for an image document, loaded via the authorised endpoint. */
function ImageThumb({ document }: { document: DocumentDto }) {
  const [url, setUrl] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let active = true;
    let objectUrl: string | null = null;

    fetchDocumentObjectUrl(document.download_url)
      .then((resolved) => {
        if (!active) {
          URL.revokeObjectURL(resolved);
          return;
        }
        objectUrl = resolved;
        setUrl(resolved);
      })
      .catch(() => {
        if (active) setFailed(true);
      });

    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [document.download_url]);

  if (failed) {
    return <FileIcon />;
  }
  if (!url) {
    return <div className="h-20 w-20 shrink-0 animate-pulse rounded-lg bg-slate-100" />;
  }
  return (
    // eslint-disable-next-line @next/next/no-img-element -- private blob URL, not a public asset
    <img
      src={url}
      alt={documentLabel(document.type)}
      className="h-20 w-20 shrink-0 rounded-lg border border-slate-200 object-cover"
    />
  );
}

function FileIcon() {
  return (
    <div className="flex h-20 w-20 shrink-0 flex-col items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500">
      <svg viewBox="0 0 24 24" className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="1.7" aria-hidden="true">
        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
        <path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z" />
      </svg>
      <span className="mt-1 text-[10px] font-semibold uppercase">PDF</span>
    </div>
  );
}

/** A single document's current state: thumbnail + name + page count. */
export function DocumentPreview({ document }: { document: DocumentDto }) {
  const pdf = document.mime === "application/pdf";
  return (
    <div className="flex items-center gap-3">
      {pdf ? <FileIcon /> : <ImageThumb document={document} />}
      <div className="min-w-0">
        <p className="truncate text-sm font-medium text-slate-800">{document.original_name}</p>
        {pdf && document.page_count != null && (
          <p className="text-xs text-slate-500">
            {document.page_count} {document.page_count === 1 ? "page" : "pages"}
          </p>
        )}
      </div>
    </div>
  );
}

export function DocumentUploader({
  type,
  pdfOnly = false,
  cameraCapture = false,
  document,
  addLabel,
  onChanged,
}: DocumentUploaderProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const cameraRef = useRef<HTMLInputElement>(null);
  const [percent, setPercent] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleFile(file: File | undefined) {
    if (!file) return;
    setError(null);

    try {
      assertWithinSizeLimit(file, type);
      const prepared = pdfOnly ? file : await prepareImage(file, type);

      setBusy(true);
      setPercent(0);
      if (document) {
        await replaceDocument(document.id, prepared, { onProgress: setPercent });
      } else {
        await uploadDocument(type, prepared, { onProgress: setPercent });
      }
      onChanged?.();
    } catch (err) {
      setError(err instanceof UploadPrepError ? err.message : errorMessage(err));
    } finally {
      setBusy(false);
      setPercent(null);
    }
  }

  async function handleDelete() {
    if (!document) return;
    setError(null);
    setBusy(true);
    try {
      await deleteDocument(document.id);
      onChanged?.();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const accept = pdfOnly ? PDF_ACCEPT : `${IMAGE_ACCEPT},${PDF_ACCEPT}`;
  const buttonLabel = addLabel ?? (document ? "Replace" : "Upload");

  return (
    <div className="space-y-3">
      {document && <DocumentPreview document={document} />}

      <div className="flex flex-wrap items-center gap-2">
        <input
          ref={inputRef}
          type="file"
          accept={accept}
          className="hidden"
          onChange={(e) => {
            void handleFile(e.target.files?.[0]);
            e.target.value = "";
          }}
        />
        <Button
          type="button"
          variant={document ? "secondary" : "primary"}
          loading={busy && percent !== null}
          disabled={busy}
          onClick={() => inputRef.current?.click()}
        >
          {buttonLabel}
        </Button>

        {cameraCapture && !pdfOnly && (
          <>
            <input
              ref={cameraRef}
              type="file"
              accept={IMAGE_ACCEPT}
              capture="environment"
              className="hidden"
              onChange={(e) => {
                void handleFile(e.target.files?.[0]);
                e.target.value = "";
              }}
            />
            <Button
              type="button"
              variant="secondary"
              disabled={busy}
              onClick={() => cameraRef.current?.click()}
            >
              Take photo
            </Button>
          </>
        )}

        {document && (
          <Button type="button" variant="ghost" disabled={busy} onClick={() => void handleDelete()}>
            Delete
          </Button>
        )}
      </div>

      {percent !== null && <Progress percent={percent} />}
      {error && <Alert tone="error">{error}</Alert>}
    </div>
  );
}
