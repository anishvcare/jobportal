"use client";

import { Button } from "@/components/ui/Button";

// Small client control for the offline fallback page. Reloading re-attempts the
// original navigation, which the service worker handles network-first.
export function OfflineRetryButton() {
  return (
    <Button type="button" onClick={() => location.reload()}>
      Try again
    </Button>
  );
}
