"use client";

import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { downloadAdminPack, downloadAdminResume } from "@/lib/admin";
import { errorMessage } from "@/lib/errors";

type Job = "resume" | "pack" | null;

/**
 * Admin résumé + Candidate Pack download buttons for a candidate detail page.
 * Mirrors the candidate self-service PackDownloads UX (busy/disabled/error).
 * Both stream through the cookie-authenticated api client.
 */
export function AdminDownloads({ profileId }: { profileId: number }) {
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
    <div className="space-y-3">
      <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <Button
          type="button"
          variant="secondary"
          loading={busy === "resume"}
          disabled={busy !== null}
          onClick={() => void run("resume", () => downloadAdminResume(profileId))}
        >
          {busy === "resume" ? "Preparing résumé…" : "Download resume (PDF)"}
        </Button>
        <Button
          type="button"
          loading={busy === "pack"}
          disabled={busy !== null}
          onClick={() => void run("pack", () => downloadAdminPack(profileId))}
        >
          {busy === "pack" ? "Preparing pack…" : "Download Candidate Pack (PDF)"}
        </Button>
      </div>
      {error && <Alert tone="error">{error}</Alert>}
    </div>
  );
}
