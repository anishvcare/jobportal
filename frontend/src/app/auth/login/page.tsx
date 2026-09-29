import type { Metadata } from "next";
import { Suspense } from "react";
import { LoginPanel } from "./LoginPanel";
import { PageLoader } from "@/components/ui/Spinner";

export const metadata: Metadata = { title: "Sign in" };

export default function LoginPage() {
  return (
    <Suspense fallback={<PageLoader />}>
      <LoginPanel />
    </Suspense>
  );
}
