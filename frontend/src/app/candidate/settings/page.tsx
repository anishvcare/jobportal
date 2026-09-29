"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { TextInput } from "@/components/ui/Field";
import { deleteAccount } from "@/lib/candidate";
import { errorMessage } from "@/lib/errors";

const CONFIRM_PHRASE = "DELETE";

export default function CandidateSettingsPage() {
  const router = useRouter();
  const { setUser } = useAuth();
  const [phrase, setPhrase] = useState("");
  const [acknowledged, setAcknowledged] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);

  const canDelete = phrase.trim().toUpperCase() === CONFIRM_PHRASE && acknowledged;

  const onDelete = async () => {
    if (!canDelete) return;
    setError(null);
    setDeleting(true);
    try {
      await deleteAccount();
      await setUser(null);
      router.replace("/");
    } catch (err) {
      setError(errorMessage(err));
      setDeleting(false);
    }
  };

  return (
    <>
      <PageHeader title="Settings" description="Manage your account." />

      <Card className="border-red-200">
        <h2 className="text-lg font-semibold text-red-700">Danger zone</h2>
        <p className="mt-2 text-sm text-slate-700">
          Deleting your account is <span className="font-semibold">permanent and immediate</span>. Your profile,
          uploaded documents (photo, Aadhaar, SSLC, certificates, passport pages) and all related data will be erased.
          This cannot be undone.
        </p>

        <div className="mt-4 space-y-3">
          <label className="flex items-start gap-3 text-sm text-slate-700">
            <input
              type="checkbox"
              className="mt-0.5 h-4 w-4 accent-red-600"
              checked={acknowledged}
              onChange={(e) => setAcknowledged(e.target.checked)}
            />
            I understand this will permanently delete my account and all my data.
          </label>

          <div>
            <label htmlFor="confirm" className="block text-sm font-medium text-slate-800">
              Type <span className="font-mono font-semibold">{CONFIRM_PHRASE}</span> to confirm
            </label>
            <TextInput
              id="confirm"
              className="mt-1 max-w-xs"
              value={phrase}
              onChange={(e) => setPhrase(e.target.value)}
              autoComplete="off"
              aria-label={`Type ${CONFIRM_PHRASE} to confirm`}
            />
          </div>

          {error && <Alert tone="error">{error}</Alert>}

          <Button variant="danger" disabled={!canDelete} loading={deleting} onClick={onDelete}>
            Delete my account
          </Button>
        </div>
      </Card>
    </>
  );
}
