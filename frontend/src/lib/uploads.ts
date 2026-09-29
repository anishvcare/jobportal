"use client";

import { mutate as globalMutate } from "swr";
import { api } from "./api";
import { PROFILE_KEY } from "./candidate";
import type { DocumentDto, DocumentType } from "./types";

/** Size limits (bytes). profile_pdf gets a larger cap; everything else 10MB. */
export const MAX_SIZE_BYTES = 10 * 1024 * 1024;
export const MAX_PROFILE_PDF_BYTES = 50 * 1024 * 1024;

/** Documents that only ever hold a single file (server replaces on re-upload). */
const SINGLE_VALUE_TYPES: ReadonlySet<DocumentType> = new Set<DocumentType>([
  "photo",
  "aadhaar_front",
  "aadhaar_back",
  "sslc",
  "cv",
  "profile_pdf",
]);

export function isSingleValueType(type: DocumentType): boolean {
  return SINGLE_VALUE_TYPES.has(type);
}

export function maxSizeBytesFor(type: DocumentType): number {
  return type === "profile_pdf" ? MAX_PROFILE_PDF_BYTES : MAX_SIZE_BYTES;
}

export function isPdf(file: File): boolean {
  return file.type === "application/pdf" || /\.pdf$/i.test(file.name);
}

export function isHeic(file: File): boolean {
  return (
    file.type === "image/heic" ||
    file.type === "image/heif" ||
    /\.(heic|heif)$/i.test(file.name)
  );
}

export function isImage(file: File): boolean {
  return file.type.startsWith("image/") || isHeic(file);
}

/** Thrown when a picked file is unusable before it ever reaches the server. */
export class UploadPrepError extends Error {}

function humanSize(bytes: number): string {
  return `${Math.round(bytes / (1024 * 1024))}MB`;
}

/** Reject too-large files with a friendly message before any conversion/upload. */
export function assertWithinSizeLimit(file: File, type: DocumentType): void {
  const limit = maxSizeBytesFor(type);
  if (file.size > limit) {
    throw new UploadPrepError(
      `That file is too large (max ${humanSize(limit)}). Please choose a smaller file.`,
    );
  }
}

function swapExtension(name: string, ext: string): string {
  const base = name.replace(/\.[^./\\]+$/, "");
  return `${base || "image"}.${ext}`;
}

/**
 * Normalise an image for upload, entirely in the browser:
 *  - HEIC/HEIF is converted to JPEG via a lazily-loaded heic2any.
 *  - The result is then compressed/normalised to JPEG (~1.5MB, max edge ~2000px).
 * PDFs (and profile_pdf) are returned untouched.
 */
export async function prepareImage(file: File, type: DocumentType): Promise<File> {
  // PDFs and the profile PDF pass through unchanged.
  if (type === "profile_pdf" || isPdf(file) || !isImage(file)) {
    return file;
  }

  let working: Blob = file;
  let outName = file.name;

  if (isHeic(file)) {
    try {
      const { default: heic2any } = await import("heic2any");
      const converted = await heic2any({ blob: file, toType: "image/jpeg", quality: 0.9 });
      working = Array.isArray(converted) ? converted[0] : converted;
      outName = swapExtension(file.name, "jpg");
    } catch {
      throw new UploadPrepError(
        "We couldn't convert that photo. Please try again, or upload a JPG or PNG instead.",
      );
    }
  }

  const inputFile =
    working instanceof File
      ? working
      : new File([working], outName, { type: "image/jpeg" });

  try {
    const { default: imageCompression } = await import("browser-image-compression");
    const compressed = await imageCompression(inputFile, {
      maxSizeMB: 1.5,
      maxWidthOrHeight: 2000,
      useWebWorker: true,
      fileType: "image/jpeg",
    });
    return new File([compressed], swapExtension(outName, "jpg"), { type: "image/jpeg" });
  } catch {
    // If compression fails but we still have a usable JPEG blob, upload that.
    if (inputFile.type === "image/jpeg") {
      return inputFile;
    }
    throw new UploadPrepError(
      "We couldn't process that image. Please try a different photo.",
    );
  }
}

export interface UploadOptions {
  onProgress?: (percent: number) => void;
  sortOrder?: number;
}

function progressHandler(onProgress?: (percent: number) => void) {
  return (event: { loaded: number; total?: number }) => {
    if (!onProgress) return;
    const total = event.total ?? 0;
    const percent = total > 0 ? Math.round((event.loaded / total) * 100) : 0;
    onProgress(percent);
  };
}

/** Upload a new document of the given type (multipart POST /candidate/documents). */
export async function uploadDocument(
  type: DocumentType,
  file: File,
  options: UploadOptions = {},
): Promise<DocumentDto> {
  const form = new FormData();
  form.append("type", type);
  form.append("file", file, file.name);
  if (options.sortOrder != null) {
    form.append("sort_order", String(options.sortOrder));
  }

  const { data } = await api.post<{ data: DocumentDto }>("/candidate/documents", form, {
    onUploadProgress: progressHandler(options.onProgress),
  });
  await refreshDocuments();
  return data.data;
}

/** Replace a specific existing document's file (POST /candidate/documents/{id}). */
export async function replaceDocument(
  id: number,
  file: File,
  options: UploadOptions = {},
): Promise<DocumentDto> {
  const form = new FormData();
  form.append("file", file, file.name);

  const { data } = await api.post<{ data: DocumentDto }>(`/candidate/documents/${id}`, form, {
    onUploadProgress: progressHandler(options.onProgress),
  });
  await refreshDocuments();
  return data.data;
}

/** Delete a document (DELETE /candidate/documents/{id}). */
export async function deleteDocument(id: number): Promise<void> {
  await api.delete(`/candidate/documents/${id}`);
  await refreshDocuments();
}

/** Reorder passport pages (PATCH /candidate/documents/reorder with the full id set). */
export async function reorderDocuments(order: number[]): Promise<DocumentDto[]> {
  const { data } = await api.patch<{ data: DocumentDto[] }>("/candidate/documents/reorder", {
    order,
  });
  await refreshDocuments();
  return data.data;
}

/** Revalidate the profile so completeness + document lists update. */
export async function refreshDocuments(): Promise<void> {
  await globalMutate(PROFILE_KEY);
}

/**
 * Fetch a private document as an authorised object URL for preview.
 * download_url is a relative "/api/..." path; the axios base already ends in
 * "/api", so strip the leading "/api" before requesting through `api`.
 */
export async function fetchDocumentObjectUrl(downloadUrl: string): Promise<string> {
  const path = downloadUrl.replace(/^\/api/, "");
  const { data } = await api.get<Blob>(path, { responseType: "blob" });
  return URL.createObjectURL(data);
}
