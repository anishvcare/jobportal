"use client";

import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { downloadCandidatePack, downloadResume } from "@/lib/candidate";
import { errorMessage } from "@/lib/errors";

type Job = "resume" | "pack" | null;

/**
 * Candidate self-service downloads: the auto-generated résumé and the full
 * Candidate Pack. Both stream through the shared api client. The pack can take a
 * few seconds to build on first request, so its button shows a "preparing" label.
 */
export function PackDownloads() {
  const [busy, setBusy] = useState<Job>(null);
  const [error, setError] = useState<string | null>(null);

  async function run(job: Exclude<Job, null>, download: () => Promise<void>): Promise<void> {
    if (busy) return;
    setError(null);
    setBusy(job);
    try {
      await download();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(null);
    }
  }

  return (
    <Card>
      <div className="mb-3">
        <h2 className="font-semibold text-slate-900">Downloads</h2>
        <p className="mt-1 text-sm text-slate-600">
          Get a clean résumé from your profile, or the full Candidate Pack (résumé plus all your
          documents) as a single PDF.
        </p>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <Button
          type="button"
          variant="secondary"
          loading={busy === "resume"}
          disabled={busy !== null}
          onClick={() => void run("resume", downloadResume)}
        >
          {busy === "resume" ? "Preparing your résumé…" : "Download my résumé (PDF)"}
        </Button>

        <Button
          type="button"
          loading={busy === "pack"}
          disabled={busy !== null}
          onClick={() => void run("pack", downloadCandidatePack)}
        >
          {busy === "pack" ? "Preparing your pack…" : "Download my Candidate Pack (PDF)"}
        </Button>
      </div>

      {error && (
        <div className="mt-3">
          <Alert tone="error">{error}</Alert>
        </div>
      )}
    </Card>
  );
}
