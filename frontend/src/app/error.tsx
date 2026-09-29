"use client";

import { Button } from "@/components/ui/Button";

export default function ErrorPage({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center">
      <h1 className="text-2xl font-bold">Something went wrong</h1>
      <p className="mt-2 text-slate-600">Please try again. If the problem continues, check your connection.</p>
      <Button onClick={reset} className="mt-6">
        Try again
      </Button>
    </div>
  );
}
