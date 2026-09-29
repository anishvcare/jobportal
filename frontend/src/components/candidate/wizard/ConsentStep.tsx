"use client";

import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import type { CandidateProfile } from "@/lib/types";
import { patchProfile } from "@/lib/candidate";
import { SaveStatus, StepError, useSaver } from "./shared";
import { StepFooter } from "./StepFooter";

function formatConsentDate(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString();
}

export function ConsentStep({
  profile,
  step,
  totalSteps,
  onFinish,
  onBack,
}: {
  profile: CandidateProfile;
  step: number;
  totalSteps: number;
  onFinish: () => void;
  onBack: () => void;
}) {
  const { state, error, run } = useSaver();
  const alreadyConsented = Boolean(profile.consent_at);
  const [checked, setChecked] = useState(alreadyConsented);
  const [showError, setShowError] = useState(false);

  const onSubmit = async () => {
    if (alreadyConsented) {
      onFinish();
      return;
    }
    if (!checked) {
      setShowError(true);
      return;
    }
    const ok = await run(() => patchProfile({ consent: true, wizard_step: Math.max(profile.wizard_step, step + 1) }));
    if (ok) onFinish();
  };

  return (
    <form
      className="space-y-4"
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        void onSubmit();
      }}
    >
      <div className="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
        <p className="font-medium text-slate-800">Who can see your information</p>
        <ul className="list-disc space-y-1 pl-5">
          <li>
            <span className="font-medium">Employers</span> can view your profile and CV when you apply to their jobs.
            They <span className="font-medium">cannot</span> view or download your uploaded documents.
          </li>
          <li>
            <span className="font-medium">Our admin team</span> can view and download your documents and application
            packs to verify your details and help place you.
          </li>
          <li>Your documents are stored on private, secured storage and are never shared publicly.</li>
        </ul>
      </div>

      {alreadyConsented ? (
        <Alert tone="success">You gave consent on {formatConsentDate(profile.consent_at as string)}.</Alert>
      ) : (
        <label className="flex items-start gap-3 rounded-lg border border-slate-300 p-4 text-sm text-slate-700">
          <input
            type="checkbox"
            className="mt-0.5 h-4 w-4 accent-brand-600"
            checked={checked}
            onChange={(e) => {
              setChecked(e.target.checked);
              setShowError(false);
            }}
          />
          <span>
            I have read and agree to the above. I consent to Nexus Flow processing my profile and documents as described.
          </span>
        </label>
      )}

      {showError && <Alert tone="error">Please tick the box to give your consent.</Alert>}
      <StepError message={error} />
      <StepFooter
        step={step}
        totalSteps={totalSteps}
        onBack={onBack}
        status={<SaveStatus state={state} />}
        nextLabel={alreadyConsented ? "Finish" : "Give consent & finish"}
        submitting={state === "saving"}
      />
    </form>
  );
}
