"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { useAuth } from "./AuthProvider";
import { homeFor } from "@/lib/auth";
import type { Role } from "@/lib/types";
import { PageLoader } from "@/components/ui/Spinner";
import { Alert } from "@/components/ui/Alert";
import { ButtonLink } from "@/components/ui/Button";
import { errorMessage } from "@/lib/errors";

/**
 * Client-side route guard for UX only. Every API endpoint enforces
 * authorization server-side; this just sends people to the right screen.
 */
export function RequireRole({ roles, children }: { roles: Role[]; children: ReactNode }) {
  const { user, isLoading, error } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  const signedOut = !isLoading && !error && !user;
  const needsOnboarding = !!user && (user.needs_onboarding || !user.role);

  useEffect(() => {
    if (signedOut) router.replace(`/auth/login?redirect=${encodeURIComponent(pathname)}`);
    else if (needsOnboarding) router.replace("/auth/choose-role");
  }, [signedOut, needsOnboarding, pathname, router]);

  if (error && !user) {
    return (
      <div className="mx-auto max-w-lg p-4">
        <Alert tone="error">{errorMessage(error)}</Alert>
      </div>
    );
  }

  if (isLoading || !user || needsOnboarding) return <PageLoader />;

  if (!user.role || !roles.includes(user.role)) {
    return (
      <div className="mx-auto max-w-lg space-y-4 p-4 text-center">
        <Alert tone="warning">This area isn&apos;t available for your account.</Alert>
        <ButtonLink href={homeFor(user)}>Go to my dashboard</ButtonLink>
      </div>
    );
  }

  return <>{children}</>;
}
