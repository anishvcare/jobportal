import type { Metadata } from "next";
import { Suspense } from "react";
import { CallbackHandler } from "./CallbackHandler";
import { PageLoader } from "@/components/ui/Spinner";

export const metadata: Metadata = { title: "Signing in" };

export default function CallbackPage() {
  return (
    <Suspense fallback={<PageLoader label="Signing you in…" />}>
      <CallbackHandler />
    </Suspense>
  );
}
