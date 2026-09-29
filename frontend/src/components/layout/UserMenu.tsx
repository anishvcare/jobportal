"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { homeFor } from "@/lib/auth";

export function UserMenu() {
  const { user, isLoading, logout } = useAuth();
  const [open, setOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (!open) return;
    const close = (event: MouseEvent) => {
      if (!menuRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("click", close);
    return () => document.removeEventListener("click", close);
  }, [open]);

  if (isLoading) return <span className="h-9 w-20 animate-pulse rounded-md bg-slate-100" aria-hidden />;

  if (!user) {
    const redirect = pathname.startsWith("/auth") ? "" : `?redirect=${encodeURIComponent(pathname)}`;
    return (
      <Link href={`/auth/login${redirect}`} className="rounded-md bg-brand-600 px-3 py-2 font-semibold text-white hover:bg-brand-700">
        Sign in
      </Link>
    );
  }

  const initials = user.name.split(/\s+/).map((part) => part[0]).slice(0, 2).join("").toUpperCase();

  return (
    <div className="relative" ref={menuRef}>
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        aria-haspopup="menu"
        className="grid h-9 w-9 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-800"
      >
        <span className="sr-only">Account menu</span>
        {initials}
      </button>
      {open && (
        <div role="menu" className="absolute right-0 mt-2 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
          <div className="border-b border-slate-100 px-4 py-2">
            <p className="truncate text-sm font-semibold">{user.name}</p>
            <p className="truncate text-xs text-slate-500">{user.email}</p>
          </div>
          <Link role="menuitem" href={homeFor(user)} onClick={() => setOpen(false)} className="block px-4 py-2.5 text-sm hover:bg-slate-50">
            My dashboard
          </Link>
          <button
            role="menuitem"
            type="button"
            className="block w-full px-4 py-2.5 text-left text-sm text-red-700 hover:bg-slate-50"
            onClick={async () => {
              setOpen(false);
              await logout();
              router.push("/");
            }}
          >
            Sign out
          </button>
        </div>
      )}
    </div>
  );
}
