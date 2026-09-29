import type { Metadata } from "next";
import { RequireRole } from "@/components/auth/RequireRole";

export const metadata: Metadata = { title: "Employer dashboard", robots: { index: false, follow: false } };

export default function EmployerLayout({ children }: LayoutProps<"/employer">) {
  return (
    <RequireRole roles={["employer"]}>
      <div className="mx-auto w-full max-w-6xl px-4 py-6 sm:py-8">{children}</div>
    </RequireRole>
  );
}
