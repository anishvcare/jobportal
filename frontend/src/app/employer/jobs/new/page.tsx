"use client";

import { EmployerNav } from "@/components/employer/EmployerNav";
import { JobForm } from "@/components/employer/JobForm";
import { PageHeader } from "@/components/ui/Card";

export default function NewJobPage() {
  return (
    <>
      <EmployerNav />
      <PageHeader title="Post a job" description="Create a draft, then publish it once your company is approved." />
      <JobForm />
    </>
  );
}
