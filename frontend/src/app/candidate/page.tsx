"use client";

import { useAuth } from "@/components/auth/AuthProvider";
import { CompletenessMeter } from "@/components/candidate/CompletenessMeter";
import { ButtonLink } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { useProfile } from "@/lib/candidate";

const STEPS = [
  {
    title: "Complete your profile",
    text: "Personal details, passport, education, skills and experience.",
    href: "/candidate/profile",
    cta: "Open profile",
  },
  {
    title: "Upload your documents",
    text: "Photo, Aadhaar, SSLC book, certificates and all passport pages.",
    href: "/candidate/documents",
    cta: "Upload documents",
  },
  {
    title: "Apply to jobs",
    text: "Apply in one tap and track your application status.",
    href: "/candidate/applications",
    cta: "My applications",
  },
];

export default function CandidateDashboard() {
  const { user } = useAuth();
  const { profile } = useProfile();

  return (
    <>
      <PageHeader
        title={`Hello, ${user?.name.split(" ")[0] ?? ""}`}
        description="Here's how to get ready for your next job."
        actions={
          <div className="flex gap-2">
            <ButtonLink href="/candidate/applications" variant="secondary">
              My applications
            </ButtonLink>
            <ButtonLink href="/candidate/settings" variant="secondary">
              Settings
            </ButtonLink>
          </div>
        }
      />

      <div className="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <ol className="grid gap-4 sm:grid-cols-3 lg:order-1">
          {STEPS.map((step, index) => (
            <li key={step.title}>
              <Card className="flex h-full flex-col">
                <span className="grid h-8 w-8 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">
                  {index + 1}
                </span>
                <h2 className="mt-3 font-semibold">{step.title}</h2>
                <p className="mt-1 flex-1 text-sm text-slate-600">{step.text}</p>
                <ButtonLink href={step.href} variant="secondary" className="mt-4 w-full">
                  {step.cta}
                </ButtonLink>
              </Card>
            </li>
          ))}
        </ol>

        {profile && (
          <aside className="lg:order-2">
            <Card>
              <CompletenessMeter completeness={profile.completeness} />
            </Card>
          </aside>
        )}
      </div>
    </>
  );
}
