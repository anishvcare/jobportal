"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Progress } from "@/components/ui/Progress";
import { BULK_EXPORT_MAX, chunkForExport, createExport, downloadExport, getExport } from "@/lib/admin";
import { errorMessage } from "@/lib/errors";
import type { BulkExport } from "@/lib/types";

const POLL_INTERVAL_MS = 2000;

/** One export batch tracked in the UI (a chunk of <=50 candidates). */
interface Batch {
  key: string;
  export: BulkExport | null;
  count: number;
  error: string | null;
  downloading: boolean;
}

/**
 * A sticky action bar for the candidate search results. It shows the current
 * selection, enforces/batches the 50-candidate cap by chunking the selection,
 * creates one export per chunk, polls each until ready/failed, and reveals a
 * working ZIP download link for each finished batch. Intervals are cleaned up
 * on unmount.
 */
export function BulkExportBar({
  selectedIds,
  onClear,
}: {
  selectedIds: number[];
  onClear: () => void;
}) {
  const [batches, setBatches] = useState<Batch[]>([]);
  const [starting, setStarting] = useState(false);
  const [startError, setStartError] = useState<string | null>(null);
  const timers = useRef<Map<string, ReturnType<typeof setInterval>>>(new Map());

  const clearAllTimers = useCallback(() => {
    timers.current.forEach((timer) => clearInterval(timer));
    timers.current.clear();
  }, []);

  useEffect(() => clearAllTimers, [clearAllTimers]);

  const poll = useCallback((key: string, exportId: number) => {
    const timer = setInterval(async () => {
      try {
        const next = await getExport(exportId);
        setBatches((prev) =>
          prev.map((batch) => (batch.key === key ? { ...batch, export: next } : batch)),
        );
        if (next.status === "ready" || next.status === "failed") {
          clearInterval(timer);
          timers.current.delete(key);
        }
      } catch (err) {
        clearInterval(timer);
        timers.current.delete(key);
        setBatches((prev) =>
          prev.map((batch) => (batch.key === key ? { ...batch, error: errorMessage(err) } : batch)),
        );
      }
    }, POLL_INTERVAL_MS);
    timers.current.set(key, timer);
  }, []);

  async function start() {
    if (starting || selectedIds.length === 0) return;
    setStartError(null);
    setStarting(true);
    clearAllTimers();

    const chunks = chunkForExport(selectedIds);
    const created: Batch[] = [];

    for (let i = 0; i < chunks.length; i += 1) {
      const chunk = chunks[i];
      const key = `${Date.now()}-${i}`;
      try {
        const exported = await createExport(chunk);
        created.push({ key, export: exported, count: chunk.length, error: null, downloading: false });
      } catch (err) {
        created.push({ key, export: null, count: chunk.length, error: errorMessage(err), downloading: false });
      }
    }

    setBatches(created);
    setStarting(false);

    for (const batch of created) {
      if (batch.export && batch.export.status !== "ready" && batch.export.status !== "failed") {
        poll(batch.key, batch.export.id);
      }
    }
  }

  async function download(batch: Batch) {
    if (!batch.export?.ready) return;
    setBatches((prev) => prev.map((b) => (b.key === batch.key ? { ...b, downloading: true, error: null } : b)));
    try {
      await downloadExport(batch.export.id);
    } catch (err) {
      setBatches((prev) => prev.map((b) => (b.key === batch.key ? { ...b, error: errorMessage(err) } : b)));
    } finally {
      setBatches((prev) => prev.map((b) => (b.key === batch.key ? { ...b, downloading: false } : b)));
    }
  }

  function reset() {
    clearAllTimers();
    setBatches([]);
    setStartError(null);
    onClear();
  }

  const count = selectedIds.length;
  const chunkCount = Math.ceil(count / BULK_EXPORT_MAX);

  return (
    <div className="sticky bottom-0 z-10 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-2px_8px_rgba(0,0,0,0.05)] backdrop-blur sm:px-6">
      <div className="mx-auto flex w-full max-w-6xl flex-col gap-3">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm text-slate-700" aria-live="polite">
            <span className="font-semibold text-slate-900">{count}</span> selected
            {count > BULK_EXPORT_MAX && (
              <span className="text-slate-500">
                {" "}
                — will be split into {chunkCount} ZIP files ({BULK_EXPORT_MAX} max each)
              </span>
            )}
          </p>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="ghost" onClick={reset} disabled={starting}>
              Clear
            </Button>
            <Button type="button" loading={starting} disabled={starting || count === 0} onClick={() => void start()}>
              Download selected (ZIP)
            </Button>
          </div>
        </div>

        {startError && <Alert tone="error">{startError}</Alert>}

        {batches.length > 0 && (
          <ul className="space-y-2">
            {batches.map((batch, index) => {
              const exp = batch.export;
              const label = batches.length > 1 ? `Batch ${index + 1} (${batch.count})` : `${batch.count} candidates`;
              return (
                <li key={batch.key} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                  <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <span className="text-sm font-medium text-slate-800">{label}</span>
                    <div className="flex items-center gap-3">
                      {exp?.status === "ready" && exp.ready && (
                        <Button
                          type="button"
                          variant="secondary"
                          loading={batch.downloading}
                          disabled={batch.downloading}
                          onClick={() => void download(batch)}
                        >
                          Download ZIP
                        </Button>
                      )}
                      {exp && (exp.status === "queued" || exp.status === "processing") && (
                        <span className="text-xs text-slate-500">Preparing… {exp.progress}%</span>
                      )}
                      {exp?.status === "failed" && <span className="text-xs text-red-700">Failed</span>}
                    </div>
                  </div>

                  {exp && (exp.status === "queued" || exp.status === "processing") && (
                    <Progress percent={exp.progress} label={`Building ZIP… ${exp.progress}%`} className="mt-2" />
                  )}

                  {(batch.error || exp?.error) && (
                    <div className="mt-2">
                      <Alert tone="error">{batch.error ?? exp?.error}</Alert>
                    </div>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}
