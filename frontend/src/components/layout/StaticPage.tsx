import type { ReactNode } from "react";

export function StaticPage({ title, updated, children }: { title: string; updated?: string; children: ReactNode }) {
  return (
    <article className="mx-auto max-w-3xl px-4 py-8 sm:py-12">
      <h1 className="text-3xl font-bold text-slate-900">{title}</h1>
      {updated && <p className="mt-1 text-sm text-slate-500">Last updated: {updated}</p>}
      <div className="prose-simple mt-6">{children}</div>
    </article>
  );
}
