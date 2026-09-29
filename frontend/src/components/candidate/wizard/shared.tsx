"use client";

import { useCallback, useRef, useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Spinner } from "@/components/ui/Spinner";
import { errorMessage } from "@/lib/errors";

export type SaveState = "idle" | "saving" | "saved" | "error";

/** Small inline indicator reused by every step. */
export function SaveStatus({ state }: { state: SaveState }) {
  if (state === "saving") {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs text-slate-500" role="status">
        <Spinner className="h-3.5 w-3.5" /> Saving…
      </span>
    );
  }
  if (state === "saved") {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs text-emerald-600" role="status">
        Saved
      </span>
    );
  }
  return null;
}

interface SaverResult {
  state: SaveState;
  error: string | null;
  /** Run an async save, tracking status; returns true on success. */
  run: (fn: () => Promise<unknown>) => Promise<boolean>;
  setError: (message: string | null) => void;
}

/**
 * Tracks saving/saved/error state around an async persistence call and
 * clears the "saved" flag after a moment.
 */
export function useSaver(): SaverResult {
  const [state, setState] = useState<SaveState>("idle");
  const [error, setError] = useState<string | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const run = useCallback(async (fn: () => Promise<unknown>): Promise<boolean> => {
    if (timer.current) clearTimeout(timer.current);
    setState("saving");
    setError(null);
    try {
      await fn();
      setState("saved");
      timer.current = setTimeout(() => setState("idle"), 2000);
      return true;
    } catch (err) {
      setState("error");
      setError(errorMessage(err));
      return false;
    }
  }, []);

  return { state, error, run, setError };
}

/** Shows a step's save error, if any. */
export function StepError({ message }: { message: string | null }) {
  if (!message) return null;
  return <Alert tone="error">{message}</Alert>;
}
