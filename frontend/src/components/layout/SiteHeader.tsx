import Link from "next/link";
import { UserMenu } from "./UserMenu";
import { APP_NAME } from "@/lib/config";

export function SiteHeader() {
  return (
    <header className="safe-top sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4">
        <Link href="/" className="flex items-center gap-2 font-bold text-brand-700">
          <span aria-hidden className="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-sm text-white">
            NF
          </span>
          <span className="text-lg">{APP_NAME}</span>
        </Link>
        <nav className="flex items-center gap-1 text-sm">
          <Link href="/jobs" className="rounded-md px-3 py-2 font-medium text-slate-700 hover:bg-slate-100">
            Jobs
          </Link>
          <UserMenu />
        </nav>
      </div>
    </header>
  );
}
