"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { AdminNav } from "@/components/admin/AdminNav";
import { AdminPhoto } from "@/components/admin/AdminPhoto";
import { BulkExportBar } from "@/components/admin/BulkExportBar";
import { CandidateFilters } from "@/components/admin/CandidateFilters";
import { Alert } from "@/components/ui/Alert";
import { Card, PageHeader } from "@/components/ui/Card";
import { Field, Select } from "@/components/ui/Field";
import { PageLoader } from "@/components/ui/Spinner";
import { useCandidateSearch, useLookups } from "@/lib/admin";
import type { CandidateSearchParams } from "@/lib/admin";
import { errorMessage } from "@/lib/errors";
import type { CandidateSummary } from "@/lib/types";

const SORTS: { value: NonNullable<CandidateSearchParams["sort"]>; label: string }[] = [
  { value: "created_at", label: "Date added" },
  { value: "name", label: "Name" },
  { value: "age", label: "Age" },
  { value: "experience", label: "Experience" },
  { value: "completeness", label: "Completeness" },
];

const PER_PAGE_OPTIONS = [20, 50, 100];

function PassportBadge({ candidate }: { candidate: CandidateSummary }) {
  if (candidate.passport_valid) {
    return <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Passport ✓</span>;
  }
  if (candidate.has_passport) {
    return <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Passport (check validity)</span>;
  }
  return <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">No passport</span>;
}

export default function AdminCandidatesPage() {
  const [filters, setFilters] = useState<CandidateSearchParams>({
    sort: "created_at",
    direction: "desc",
    per_page: 20,
    page: 1,
  });
  const [selected, setSelected] = useState<Set<number>>(new Set());

  const { lookups } = useLookups();
  const defaultCountryId = lookups?.countries[0]?.id ?? null;

  const { results, meta, isLoading, error } = useCandidateSearch(filters);

  // Patch filters and reset to page 1 for anything other than a page change.
  function patch(next: Partial<CandidateSearchParams>) {
    setFilters((prev) => ({ ...prev, ...next, page: 1 }));
  }

  function reset() {
    setFilters({ sort: "created_at", direction: "desc", per_page: filters.per_page, page: 1 });
  }

  function toggle(id: number) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  const pageIds = useMemo(() => results.map((c) => c.id), [results]);
  const allOnPageSelected = pageIds.length > 0 && pageIds.every((id) => selected.has(id));

  function toggleAllOnPage() {
    setSelected((prev) => {
      const next = new Set(prev);
      if (allOnPageSelected) pageIds.forEach((id) => next.delete(id));
      else pageIds.forEach((id) => next.add(id));
      return next;
    });
  }

  const selectedIds = useMemo(() => [...selected], [selected]);
  const currentPage = meta?.current_page ?? filters.page ?? 1;
  const lastPage = meta?.last_page ?? 1;

  return (
    <>
      <AdminNav />
      <PageHeader
        title="Candidate search"
        description="Filter candidates and download packs individually or in bulk."
      />

      <Card className="mb-6">
        <CandidateFilters filters={filters} onChange={patch} onReset={reset} countryId={defaultCountryId} />
      </Card>

      {/* Per-trade counts over the whole filtered set */}
      {meta && meta.trade_counts.length > 0 && (
        <div className="mb-4 flex flex-wrap gap-2">
          {meta.trade_counts.map((tc) => (
            <span
              key={tc.trade_id}
              className="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700"
            >
              {tc.trade_name}
              <span className="ml-1 text-slate-500">{tc.count}</span>
            </span>
          ))}
        </div>
      )}

      {/* Sort + count + per page */}
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <p className="text-sm text-slate-600" aria-live="polite">
          {meta ? `${meta.total} candidate${meta.total === 1 ? "" : "s"} found` : "Loading…"}
        </p>
        <div className="flex flex-wrap gap-3">
          <Field label="Sort by" htmlFor="sort">
            <Select
              id="sort"
              value={filters.sort ?? "created_at"}
              onChange={(e) => patch({ sort: e.target.value as CandidateSearchParams["sort"] })}
            >
              {SORTS.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Direction" htmlFor="direction">
            <Select
              id="direction"
              value={filters.direction ?? "desc"}
              onChange={(e) => patch({ direction: e.target.value as CandidateSearchParams["direction"] })}
            >
              <option value="desc">Descending</option>
              <option value="asc">Ascending</option>
            </Select>
          </Field>
          <Field label="Per page" htmlFor="per-page">
            <Select
              id="per-page"
              value={filters.per_page ?? 20}
              onChange={(e) => patch({ per_page: Number(e.target.value) })}
            >
              {PER_PAGE_OPTIONS.map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </Select>
          </Field>
        </div>
      </div>

      {error ? (
        <Alert tone="error">{errorMessage(error)}</Alert>
      ) : isLoading && results.length === 0 ? (
        <PageLoader label="Searching candidates…" />
      ) : results.length === 0 ? (
        <Card>
          <p className="text-sm text-slate-600">No candidates match these filters.</p>
        </Card>
      ) : (
        <>
          {/* Desktop table */}
          <div className="hidden overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm md:block">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-3 py-3">
                    <input
                      type="checkbox"
                      checked={allOnPageSelected}
                      onChange={toggleAllOnPage}
                      aria-label="Select all on page"
                    />
                  </th>
                  <th className="px-3 py-3">Candidate</th>
                  <th className="px-3 py-3">Age</th>
                  <th className="px-3 py-3">Gender</th>
                  <th className="px-3 py-3">District</th>
                  <th className="px-3 py-3">Trades</th>
                  <th className="px-3 py-3">Exp.</th>
                  <th className="px-3 py-3">Complete</th>
                  <th className="px-3 py-3">Passport</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {results.map((c) => (
                  <tr key={c.id} className="hover:bg-slate-50">
                    <td className="px-3 py-3">
                      <input
                        type="checkbox"
                        checked={selected.has(c.id)}
                        onChange={() => toggle(c.id)}
                        aria-label={`Select ${c.full_name ?? "candidate"}`}
                      />
                    </td>
                    <td className="px-3 py-3">
                      <Link href={`/admin/candidates/${c.id}`} className="flex items-center gap-3 font-medium text-brand-700 hover:underline">
                        <AdminPhoto photoUrl={c.photo_url} alt="" className="h-10 w-10 shrink-0 rounded-full" />
                        {c.full_name ?? "(no name)"}
                      </Link>
                    </td>
                    <td className="px-3 py-3 text-slate-700">{c.age ?? "—"}</td>
                    <td className="px-3 py-3 capitalize text-slate-700">{c.gender ?? "—"}</td>
                    <td className="px-3 py-3 text-slate-700">{c.district ?? "—"}</td>
                    <td className="px-3 py-3 text-slate-700">{c.trades.length ? c.trades.join(", ") : "—"}</td>
                    <td className="px-3 py-3 text-slate-700">{c.experience_years} yr</td>
                    <td className="px-3 py-3 text-slate-700">{c.completeness}%</td>
                    <td className="px-3 py-3">
                      <PassportBadge candidate={c} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile cards */}
          <div className="space-y-3 md:hidden">
            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" checked={allOnPageSelected} onChange={toggleAllOnPage} />
              Select all on page
            </label>
            {results.map((c) => (
              <Card key={c.id}>
                <div className="flex items-start gap-3">
                  <input
                    type="checkbox"
                    className="mt-1"
                    checked={selected.has(c.id)}
                    onChange={() => toggle(c.id)}
                    aria-label={`Select ${c.full_name ?? "candidate"}`}
                  />
                  <AdminPhoto photoUrl={c.photo_url} alt="" className="h-14 w-14 shrink-0 rounded-full" />
                  <div className="min-w-0 flex-1">
                    <Link href={`/admin/candidates/${c.id}`} className="font-semibold text-brand-700 hover:underline">
                      {c.full_name ?? "(no name)"}
                    </Link>
                    <p className="mt-0.5 text-sm text-slate-600">
                      {c.age ?? "—"} yrs · <span className="capitalize">{c.gender ?? "—"}</span> · {c.district ?? "—"}
                    </p>
                    <p className="mt-0.5 text-sm text-slate-600">
                      {c.trades.length ? c.trades.join(", ") : "No trades"} · {c.experience_years} yr exp · {c.completeness}%
                    </p>
                    <div className="mt-2">
                      <PassportBadge candidate={c} />
                    </div>
                  </div>
                </div>
              </Card>
            ))}
          </div>

          {/* Pagination */}
          <div className="mt-6 flex items-center justify-between">
            <button
              type="button"
              className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              disabled={currentPage <= 1}
              onClick={() => setFilters((prev) => ({ ...prev, page: currentPage - 1 }))}
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
              onClick={() => setFilters((prev) => ({ ...prev, page: currentPage + 1 }))}
            >
              Next
            </button>
          </div>
        </>
      )}

      {selectedIds.length > 0 && (
        <BulkExportBar selectedIds={selectedIds} onClear={() => setSelected(new Set())} />
      )}
    </>
  );
}
