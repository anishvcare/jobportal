"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { ButtonLink } from "@/components/ui/Button";
import { PageLoader } from "@/components/ui/Spinner";
import { api } from "@/lib/api";
import { homeFor, safeRedirect } from "@/lib/auth";
import { errorMessage } from "@/lib/errors";
import type { User } from "@/lib/types";

/**
 * Receives the single-use code from the API's Google callback and exchanges
 * it for a session from THIS browsing context, so the cookie lands in the
 * installed PWA's cookie jar as well as in normal browser tabs.
 */
export function CallbackHandler() {
  const params = useSearchParams();
  const router = useRouter();
  const { setUser } = useAuth();
  const [error, setError] = useState<string | null>(null);
  // The code is single-use: guard against React Strict Mode double-invoking effects.
  const started = useRef(false);

  useEffect(() => {
    if (started.current) return;
    started.current = true;

    const code = params.get("code");
    const redirect = safeRedirect(params.get("redirect"));

    if (!code) {
      router.replace("/auth/login?error=failed");
      return;
    }

    // Remove the code from the address bar and history straight away.
    window.history.replaceState(null, "", "/auth/callback");

    api
      .post<{ data: User }>("/auth/exchange", { code })
      .then(async ({ data }) => {
        await setUser(data.data);
        const destination = data.data.needs_onboarding ? "/auth/choose-role" : redirect ?? homeFor(data.data);
        router.replace(destination);
      })
      .catch((err: unknown) => setError(errorMessage(err, "Your sign-in link has expired. Please sign in again.")));
  }, [params, router, setUser]);

  if (error) {
    return (
      <div className="space-y-4 text-center">
        <Alert tone="error">{error}</Alert>
        <ButtonLink href="/auth/login">Try again</ButtonLink>
      </div>
    );
  }

  return <PageLoader label="Signing you in…" />;
}
