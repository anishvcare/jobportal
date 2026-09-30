import type { Metadata } from "next";
import { ButtonLink } from "@/components/ui/Button";
import { OfflineRetryButton } from "@/components/pwa/OfflineRetryButton";

export const metadata: Metadata = {
  title: "Offline",
};

// App-shell offline fallback. Precached by the service worker and served on
// failed navigations. Renders inside the root layout, so SiteHeader/SiteFooter
// remain visible around this content.
export default function OfflinePage() {
  return (
    <div className="safe-top safe-bottom mx-auto flex max-w-md flex-col items-center gap-4 px-4 py-16 text-center">
      <span className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-2xl" aria-hidden>
        📡
      </span>
      <h1 className="text-2xl font-bold text-slate-900">You’re offline</h1>
      <p className="text-slate-600">
        We couldn’t reach Nexus Flow right now. Check your connection and try again. Pages you’ve already visited may
        still be available.
      </p>
      <div className="mt-2 flex flex-wrap items-center justify-center gap-3">
        <OfflineRetryButton />
        <ButtonLink href="/" variant="secondary">
          Go to home
        </ButtonLink>
      </div>
    </div>
  );
}
