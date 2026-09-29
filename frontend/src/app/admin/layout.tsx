import type { Metadata } from "next";
import { RequireRole } from "@/components/auth/RequireRole";

export const metadata: Metadata = { title: "Admin dashboard", robots: { index: false, follow: false } };

export default function AdminLayout({ children }: LayoutProps<"/admin">) {
  return (
    <RequireRole roles={["admin"]}>
      <div className="mx-auto w-full max-w-6xl px-4 py-6 sm:py-8">{children}</div>
    </RequireRole>
  );
}
