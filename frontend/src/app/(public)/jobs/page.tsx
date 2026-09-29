import type { Metadata } from "next";
import { ButtonLink } from "@/components/ui/Button";

export const metadata: Metadata = {
  title: "Jobs",
  description: "Browse jobs in Kerala, India, Russia and worldwide.",
};

// Replaced by the full job board (filters, pagination) in the jobs milestone.
export default function JobsPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12 text-center">
      <h1 className="text-2xl font-bold">Jobs</h1>
      <p className="mt-3 text-slate-600">Job listings are coming soon. Create your profile now so you are ready to apply.</p>
      <ButtonLink href="/auth/login?as=candidate" className="mt-6">
        Create my profile
      </ButtonLink>
    </div>
  );
}
