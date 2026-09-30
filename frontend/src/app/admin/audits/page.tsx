"use client";

import { useState } from "react";
import { AdminNav } from "@/components/admin/AdminNav";
import { Card, PageHeader } from "@/components/ui/Card";
import { ErrorState } from "@/components/ui/ErrorState";
import { Field, Select } from "@/components/ui/Field";
import { PageLoader } from "@/components/ui/Spinner";
import { useDownloadAudits } from "@/lib/admin";
import type { DownloadAuditKind } from "@/lib/types";

const KIND_LABELS: Record<DownloadAuditKind, string> = {
  document: "Document",
  resume: "Resume",
  pack: "Candidate Pack",
  bulk_zip: "Bulk ZIP",
};

function formatWhen(iso: string | null): string {
  if (!iso) return "—";
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString();
}

export default function AdminAuditsPage() {
  const [kind, setKind] = useState<DownloadAuditKind | "">("");
  const [page, setPage] = useState(1);

  const { audits, meta, isLoading, error, mutate } = useDownloadAudits({
    kind: kind || undefined,
    page,
    per_page: 20,
  });

  const currentPage = meta?.current_page ?? page;
  const lastPage = meta?.last_page ?? 1;

  return (
    <>
      <AdminNav />
      <PageHeader title="Download audit log" description="Every document and pack download: who, whose, and when." />

      <div className="mb-4 max-w-xs">
        <Field label="Filter by kind" htmlFor="kind">
          <Select
            id="kind"
            value={kind}
            onChange={(e) => {
              setKind(e.target.value as DownloadAuditKind | "");
              setPage(1);
            }}
          >
            <option value="">All kinds</option>
            <option value="document">Document</option>
            <option value="resume">Resume</option>
            <option value="pack">Candidate Pack</option>
            <option value="bulk_zip">Bulk ZIP</option>
          </Select>
        </Field>
      </div>

      {error ? (
        <ErrorState error={error} onRetry={() => void mutate()} />
      ) : isLoading && audits.length === 0 ? (
        <PageLoader label="Loading audit log…" />
      ) : audits.length === 0 ? (
        <Card>
          <p className="text-sm text-slate-600">No downloads have been recorded yet.</p>
        </Card>
      ) : (
        <>
          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-3 py-3">Actor</th>
                  <th className="px-3 py-3">Candidate</th>
                  <th className="px-3 py-3">Kind</th>
                  <th className="px-3 py-3">When</th>
                  <th className="px-3 py-3">IP</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {audits.map((a) => (
                  <tr key={a.id} className="hover:bg-slate-50">
                    <td className="px-3 py-3">
                      {a.actor ? (
                        <>
                          <span className="font-medium text-slate-900">{a.actor.name}</span>
                          <span className="block text-xs text-slate-500">{a.actor.email}</span>
                        </>
                      ) : (
                        <span className="text-slate-500">(unknown)</span>
                      )}
                    </td>
                    <td className="px-3 py-3 text-slate-700">
                      {a.candidate_name ?? <span className="italic text-slate-400">(deleted)</span>}
                    </td>
                    <td className="px-3 py-3 text-slate-700">{KIND_LABELS[a.kind] ?? a.kind}</td>
                    <td className="px-3 py-3 text-slate-700">{formatWhen(a.created_at)}</td>
                    <td className="px-3 py-3 text-slate-500">{a.ip ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mt-6 flex items-center justify-between">
            <button
              type="button"
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              disabled={currentPage <= 1}
              onClick={() => setPage(currentPage - 1)}
            >
              Previous
            </button>
            <span className="text-sm text-slate-600">
              Page {currentPage} of {lastPage}
            </span>
            <button
              type="button"
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              disabled={currentPage >= lastPage}
              onClick={() => setPage(currentPage + 1)}
            >
              Next
            </button>
          </div>
        </>
      )}
    </>
  );
}
