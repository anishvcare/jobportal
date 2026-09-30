"use client";

import { useAuth } from "@/components/auth/AuthProvider";
import { EmployerNav } from "@/components/employer/EmployerNav";
import { Alert } from "@/components/ui/Alert";
import { ButtonLink } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { PageLoader } from "@/components/ui/Spinner";
import { useEmployerProfile } from "@/lib/employer";
import { errorMessage } from "@/lib/errors";

const QUICK_LINKS = [
  { title: "Company profile", text: "Keep your company details and logo up to date.", href: "/employer/profile", cta: "Edit profile" },
  { title: "Post a job", text: "Create a new job and publish it to the board.", href: "/employer/jobs/new", cta: "New job" },
  { title: "Manage jobs", text: "Publish, close and review applicants for your jobs.", href: "/employer/jobs", cta: "View jobs" },
];

export default function EmployerDashboard() {
  const { user } = useAuth();
  const { profile, isLoading, error } = useEmployerProfile();

  return (
    <>
      <EmployerNav />
      <PageHeader
        title={`Welcome, ${user?.name.split(" ")[0] ?? ""}`}
        description="Manage your company, jobs and applicants."
      />

      {error ? (
        <Alert tone="error">{errorMessage(error)}</Alert>
      ) : isLoading || !profile ? (
        <PageLoader label="Loading your dashboard…" />
      ) : profile.status === "pending" ? (
        <Alert tone="info">
          Your account is awaiting approval. Our team reviews new employers before their jobs can go live. You can still
          set up your company profile and draft jobs in the meantime.
        </Alert>
      ) : profile.status === "suspended" ? (
        <Alert tone="warning">
          Your employer account has been suspended. You cannot publish jobs while suspended. Please contact support if
          you think this is a mistake.
        </Alert>
      ) : (
        <div className="grid gap-4 sm:grid-cols-3">
          {QUICK_LINKS.map((link) => (
            <Card key={link.title} className="flex h-full flex-col">
              <h2 className="font-semibold">{link.title}</h2>
              <p className="mt-1 flex-1 text-sm text-slate-600">{link.text}</p>
              <ButtonLink href={link.href} variant="secondary" className="mt-4 w-full">
                {link.cta}
              </ButtonLink>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}
