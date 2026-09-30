"use client";

import { useEffect } from "react";

// Registers the hand-written classic service worker at /sw.js with scope "/".
// updateViaCache: "none" ensures the SW script itself is never served from the
// HTTP cache (reinforcing the no-store header in next.config.ts). Registration
// is guarded so it runs at most once and never throws in dev.
export function ServiceWorkerRegister() {
  useEffect(() => {
    if (typeof navigator === "undefined" || !("serviceWorker" in navigator)) {
      return;
    }

    const register = () => {
      navigator.serviceWorker
        .register("/sw.js", { scope: "/", updateViaCache: "none" })
        .catch((error) => {
          // Swallow quietly: a failed SW registration must never break the app.
          console.debug("Service worker registration failed", error);
        });
    };

    if (document.readyState === "complete") {
      register();
    } else {
      window.addEventListener("load", register, { once: true });
      return () => window.removeEventListener("load", register);
    }
  }, []);

  return null;
}
