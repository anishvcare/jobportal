"use client";

import { useCallback, useEffect, useState, useSyncExternalStore } from "react";
import { buttonClasses } from "@/components/ui/Button";

// Minimal local type for the non-standard beforeinstallprompt event. It is not
// part of lib.dom.d.ts, so we describe just the parts we use. This keeps the
// component eslint-clean (no bare `any`) under eslint-config-next/typescript.
interface BeforeInstallPromptEvent extends Event {
  readonly platforms: string[];
  readonly userChoice: Promise<{ outcome: "accepted" | "dismissed"; platform: string }>;
  prompt(): Promise<void>;
}

function isStandalone(): boolean {
  if (typeof window === "undefined") return false;
  const displayModeStandalone = window.matchMedia?.("(display-mode: standalone)").matches ?? false;
  // iOS Safari exposes navigator.standalone (not in the standard lib types).
  const iosStandalone = (window.navigator as Navigator & { standalone?: boolean }).standalone === true;
  return displayModeStandalone || iosStandalone;
}

function isIosSafari(): boolean {
  if (typeof window === "undefined") return false;
  const ua = window.navigator.userAgent;
  const isIos = /iPad|iPhone|iPod/.test(ua);
  // MSStream check excludes old IE on Windows Phone that spoofs iOS UA strings.
  const notMsStream = !(window as Window & { MSStream?: unknown }).MSStream;
  return isIos && notMsStream;
}

// useSyncExternalStore gives us a stable "are we on the client yet?" flag
// without calling setState inside an effect (which the react-hooks lint rule
// forbids). The server snapshot is always false, so nothing is rendered until
// hydration completes — this is what prevents any hydration mismatch.
const emptySubscribe = () => () => {};
function useIsClient(): boolean {
  return useSyncExternalStore(
    emptySubscribe,
    () => true,
    () => false,
  );
}

// InstallPrompt renders a custom "Install app" button when the browser fires
// beforeinstallprompt (Android/Chromium). On iOS Safari — where that event
// never fires — it shows short "Add to Home Screen" instructions instead. It
// renders nothing when the app is already installed/standalone, and renders
// nothing during SSR/first paint to avoid any hydration mismatch.
export function InstallPrompt() {
  const isClient = useIsClient();
  const [installEvent, setInstallEvent] = useState<BeforeInstallPromptEvent | null>(null);
  const [installed, setInstalled] = useState(false);

  useEffect(() => {
    const onBeforeInstallPrompt = (event: Event) => {
      // Stop Chrome's default mini-infobar; we surface our own button instead.
      event.preventDefault();
      setInstallEvent(event as BeforeInstallPromptEvent);
    };

    const onAppInstalled = () => {
      setInstallEvent(null);
      setInstalled(true);
    };

    window.addEventListener("beforeinstallprompt", onBeforeInstallPrompt);
    window.addEventListener("appinstalled", onAppInstalled);

    return () => {
      window.removeEventListener("beforeinstallprompt", onBeforeInstallPrompt);
      window.removeEventListener("appinstalled", onAppInstalled);
    };
  }, []);

  const handleInstall = useCallback(async () => {
    if (!installEvent) return;
    await installEvent.prompt();
    await installEvent.userChoice;
    // The event can only be used once; clear it either way to hide the button.
    setInstallEvent(null);
  }, [installEvent]);

  // Nothing until the client is hydrated; also hide once installed/standalone.
  if (!isClient || installed || isStandalone()) {
    return null;
  }

  if (isIosSafari()) {
    return (
      <aside
        aria-label="Install this app"
        className="safe-bottom rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-slate-700"
      >
        <p className="font-semibold text-slate-900">Install Nexus Flow</p>
        <p className="mt-1">
          Tap the Share icon{" "}
          <span aria-hidden="true" className="mx-0.5 inline-block align-text-bottom">
            {/* iOS Share glyph */}
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" className="inline">
              <path
                d="M12 3v11m0-11 3.5 3.5M12 3 8.5 6.5"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
              <path
                d="M6 11H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-1"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </span>
          in the toolbar, then choose <strong>Add to Home Screen</strong>.
        </p>
      </aside>
    );
  }

  if (!installEvent) {
    return null;
  }

  return (
    <aside
      aria-label="Install this app"
      className="safe-bottom flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4"
    >
      <div className="text-sm text-slate-700">
        <p className="font-semibold text-slate-900">Install Nexus Flow</p>
        <p className="mt-0.5">Add the app to your home screen for faster access, even offline.</p>
      </div>
      <button type="button" onClick={handleInstall} className={buttonClasses("primary")}>
        Install app
      </button>
    </aside>
  );
}
