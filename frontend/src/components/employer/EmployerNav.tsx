"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const LINKS = [
  { href: "/employer", label: "Dashboard" },
  { href: "/employer/profile", label: "Company profile" },
  { href: "/employer/jobs", label: "Jobs" },
] as const;

/** Simple tab-style navigation across the employer pages. */
export function EmployerNav() {
  const pathname = usePathname();

  return (
    <nav className="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-3" aria-label="Employer">
      {LINKS.map((link) => {
        const active = link.href === "/employer" ? pathname === "/employer" : pathname.startsWith(link.href);
        return (
          <Link
            key={link.href}
            href={link.href}
            aria-current={active ? "page" : undefined}
            className={`rounded-lg px-3 py-2 text-sm font-semibold transition-colors ${
              active ? "bg-brand-600 text-white" : "text-slate-700 hover:bg-slate-100"
            }`}
          >
            {link.label}
          </Link>
        );
      })}
    </nav>
  );
}
