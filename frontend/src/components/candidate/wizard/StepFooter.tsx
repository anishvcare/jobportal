"use client";

import type { ReactNode } from "react";
import { Button } from "@/components/ui/Button";

/**
 * Shared footer for a wizard step: a Back button (when not on the first step),
 * an inline save indicator, and a submit button that advances to the next step.
 */
export function StepFooter({
  step,
  totalSteps,
  status,
  onBack,
  nextLabel,
  submitting = false,
}: {
  step: number;
  totalSteps: number;
  status?: ReactNode;
  onBack?: () => void;
  nextLabel?: string;
  submitting?: boolean;
}) {
  const isLast = step === totalSteps - 1;
  const label = nextLabel ?? (isLast ? "Finish" : "Save & continue");

  return (
    <div className="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
      <div className="flex items-center gap-3">
        {step > 0 && onBack && (
          <Button type="button" variant="secondary" onClick={onBack}>
            Back
          </Button>
        )}
        {status}
      </div>
      <Button type="submit" loading={submitting}>
        {label}
      </Button>
    </div>
  );
}
