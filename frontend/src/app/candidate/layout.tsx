import type { Metadata } from "next";
import { RequireRole } from "@/components/auth/RequireRole";

export const metadata: Metadata = { title: "Candidate dashboard", robots: { index: false, follow: false } };

export default function CandidateLayout({ children }: LayoutProps<"/candidate">) {
  return (
    <RequireRole roles={["candidate"]}>
      <div className="mx-auto w-full max-w-6xl px-4 py-6 sm:py-8">{children}</div>
    </RequireRole>
  );
}
