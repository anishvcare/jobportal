"use client";

import { Alert } from "./Alert";
import { Button } from "./Button";
import { errorMessage } from "@/lib/errors";

/**
 * Error state with a retry action for SWR-driven lists.
 * Renders a friendly error message plus a "Try again" button wired to onRetry
 * (typically the SWR hook's mutate()).
 */
export function ErrorState({
  error,
  onRetry,
  retrying = false,
}: {
  error: unknown;
  onRetry: () => void;
  retrying?: boolean;
}) {
  return (
    <Alert tone="error">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <span>{errorMessage(error)}</span>
        <Button variant="secondary" loading={retrying} onClick={onRetry} className="self-start sm:self-auto">
          Try again
        </Button>
      </div>
    </Alert>
  );
}
