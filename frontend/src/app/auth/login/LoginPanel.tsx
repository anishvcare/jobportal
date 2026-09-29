"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Card } from "@/components/ui/Card";
import { Spinner } from "@/components/ui/Spinner";
import { googleLoginUrl, homeFor, rememberRoleIntent, safeRedirect } from "@/lib/auth";

const ERRORS: Record<string, string> = {
  cancelled: "Sign-in was cancelled. You can try again whenever you're ready.",
  expired: "Your sign-in took too long or expired. Please try again.",
  failed: "We couldn't sign you in with Google. Please try again.",
  unverified: "Your Google account email isn't verified. Please verify it with Google and try again.",
};

export function LoginPanel() {
  const params = useSearchParams();
  const router = useRouter();
  const { user, isLoading } = useAuth();
  const [redirecting, setRedirecting] = useState(false);

  const redirect = safeRedirect(params.get("redirect"));
  const as = params.get("as");
  const error = params.get("error");

  useEffect(() => {
    if (as === "candidate" || as === "employer") rememberRoleIntent(as);
  }, [as]);

  // Already signed in: skip the login screen.
  useEffect(() => {
    if (!isLoading && user) router.replace(user.needs_onboarding ? "/auth/choose-role" : redirect ?? homeFor(user));
  }, [isLoading, user, redirect, router]);

  const heading =
    as === "employer" ? "Sign in to post jobs" : as === "candidate" ? "Sign in to create your profile" : "Sign in to Nexus Flow";

  return (
    <Card>
      <h1 className="text-2xl font-bold">{heading}</h1>
      <p className="mt-2 text-sm text-slate-600">
        Use your Google account. It&apos;s quick, secure and you don&apos;t need another password.
      </p>

      {error && (
        <div className="mt-4">
          <Alert tone="error">{ERRORS[error] ?? ERRORS.failed}</Alert>
        </div>
      )}

      {/* A plain full-page link (not a popup) so sign-in also works inside the installed app. */}
      <a
        href={googleLoginUrl(redirect)}
        onClick={() => setRedirecting(true)}
        aria-disabled={redirecting}
        className="mt-6 flex min-h-12 w-full items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
      >
        {redirecting ? <Spinner className="h-5 w-5" /> : <GoogleIcon />}
        {redirecting ? "Opening Google…" : "Continue with Google"}
      </a>

      <p className="mt-6 text-xs text-slate-500">
        By continuing you agree to our <Link className="underline" href="/terms">Terms</Link> and{" "}
        <Link className="underline" href="/privacy">Privacy Policy</Link>.
      </p>
    </Card>
  );
}

function GoogleIcon() {
  return (
    <svg viewBox="0 0 48 48" className="h-5 w-5" aria-hidden="true">
      <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z" />
      <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
      <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z" />
      <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z" />
    </svg>
  );
}
