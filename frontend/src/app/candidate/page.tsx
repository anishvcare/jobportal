"use client";

import { useAuth } from "@/components/auth/AuthProvider";
import { Card, PageHeader } from "@/components/ui/Card";

const STEPS = [
  { title: "Complete your profile", text: "Personal details, passport, education, skills and experience." },
  { title: "Upload your documents", text: "Photo, Aadhaar, SSLC book, certificates and all passport pages." },
  { title: "Apply to jobs", text: "Apply in one tap and track your application status." },
];

export default function CandidateDashboard() {
  const { user } = useAuth();

  return (
    <>
      <PageHeader title={`Hello, ${user?.name.split(" ")[0] ?? ""}`} description="Here's how to get ready for your next job." />
      <ol className="grid gap-4 sm:grid-cols-3">
        {STEPS.map((step, index) => (
          <li key={step.title}>
            <Card className="h-full">
              <span className="grid h-8 w-8 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{index + 1}</span>
              <h2 className="mt-3 font-semibold">{step.title}</h2>
              <p className="mt-1 text-sm text-slate-600">{step.text}</p>
            </Card>
          </li>
        ))}
      </ol>
    </>
  );
}
