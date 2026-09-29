"use client";

import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { DocumentPreview, DocumentUploader } from "./DocumentUploader";
import { errorMessage } from "@/lib/errors";
import type { DocumentDto } from "@/lib/types";
import { reorderDocuments } from "@/lib/uploads";

/**
 * Passport pages: multiple images or PDFs, reorderable via up/down buttons.
 * Reorder sends the FULL set of passport ids in the desired order.
 */
export function PassportSection({
  pages,
  onChanged,
}: {
  pages: DocumentDto[];
  onChanged?: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const ordered = [...pages].sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);

  async function move(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= ordered.length) return;

    const order = ordered.map((doc) => doc.id);
    [order[index], order[target]] = [order[target], order[index]];

    setBusy(true);
    setError(null);
    try {
      await reorderDocuments(order);
      onChanged?.();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4">
      {ordered.length > 0 && (
        <ul className="space-y-3">
          {ordered.map((doc, index) => (
            <li
              key={doc.id}
              className="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-3"
            >
              <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">
                {index + 1}
              </span>
              <div className="min-w-0 flex-1">
                <DocumentPreview document={doc} />
              </div>
              <div className="flex items-center gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  className="min-h-9 px-2"
                  aria-label="Move up"
                  disabled={busy || index === 0}
                  onClick={() => void move(index, -1)}
                >
                  ↑
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  className="min-h-9 px-2"
                  aria-label="Move down"
                  disabled={busy || index === ordered.length - 1}
                  onClick={() => void move(index, 1)}
                >
                  ↓
                </Button>
                <DocumentUploader type="passport" document={doc} onChanged={onChanged} />
              </div>
            </li>
          ))}
        </ul>
      )}

      {error && <Alert tone="error">{error}</Alert>}

      <DocumentUploader
        type="passport"
        cameraCapture
        addLabel="Add passport page"
        onChanged={onChanged}
      />
    </div>
  );
}
