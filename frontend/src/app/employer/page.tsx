"use client";

import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { PageHeader } from "@/components/ui/Card";

export default function EmployerDashboard() {
  const { user } = useAuth();

  return (
    <>
      <PageHeader title={`Welcome, ${user?.name.split(" ")[0] ?? ""}`} description="Manage your company, jobs and applicants." />
      <Alert tone="info">
        Next step: add your company profile. Our team approves new employers before their jobs go live.
      </Alert>
    </>
  );
}
