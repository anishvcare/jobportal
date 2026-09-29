"use client";

import useSWR from "swr";
import { Alert } from "@/components/ui/Alert";
import { Card, PageHeader } from "@/components/ui/Card";
import { AdminNav } from "@/components/admin/AdminNav";
import { fetcher } from "@/lib/api";
import { errorMessage } from "@/lib/errors";
import type { AdminDashboard } from "@/lib/types";

const STATS: { key: keyof AdminDashboard; label: string }[] = [
  { key: "candidates", label: "Candidates" },
  { key: "employers", label: "Employers" },
  { key: "pending_onboarding", label: "Signed up, no role yet" },
];

export default function AdminDashboardPage() {
  const { data, error, isLoading } = useSWR<AdminDashboard>("/admin/dashboard", fetcher);

  return (
    <>
      <AdminNav />
      <PageHeader title="Admin dashboard" description="Platform overview." />
      {error ? (
        <Alert tone="error">{errorMessage(error)}</Alert>
      ) : (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
          {STATS.map((stat) => (
            <Card key={stat.key}>
              <p className="text-sm text-slate-600">{stat.label}</p>
              <p className="mt-1 text-3xl font-bold text-slate-900">
                {isLoading || !data ? <span className="inline-block h-8 w-12 animate-pulse rounded bg-slate-100" /> : data[stat.key]}
              </p>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}
