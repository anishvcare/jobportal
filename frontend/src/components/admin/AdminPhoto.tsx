"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";

/**
 * A candidate photo thumbnail. The photo endpoint is a private, cookie-
 * authenticated admin route on a different origin, so a bare <img src> cannot
 * reliably send credentials. Instead we fetch it as a blob through the shared
 * api client (cookies + XSRF apply) and render an object URL, which is revoked
 * on unmount / when the source changes.
 */
export function AdminPhoto({
  photoUrl,
  alt,
  className = "",
}: {
  /** Relative API path like "/api/admin/candidates/12/photo", or null. */
  photoUrl: string | null;
  alt: string;
  className?: string;
}) {
  const [objectUrl, setObjectUrl] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (!photoUrl) return;

    let revoked = false;
    let created: string | null = null;

    // photo_url is prefixed with "/api"; the api client baseURL already ends in
    // "/api", so strip the leading "/api" before requesting.
    const path = photoUrl.replace(/^\/api/, "");

    api
      .get<Blob>(path, { responseType: "blob" })
      .then(({ data }) => {
        if (revoked) return;
        created = URL.createObjectURL(data);
        setFailed(false);
        setObjectUrl(created);
      })
      .catch(() => {
        if (!revoked) setFailed(true);
      });

    return () => {
      revoked = true;
      if (created) URL.revokeObjectURL(created);
      setObjectUrl(null);
    };
  }, [photoUrl]);

  if (photoUrl && objectUrl && !failed) {
    // eslint-disable-next-line @next/next/no-img-element -- blob object URL, not a static asset
    return <img src={objectUrl} alt={alt} className={`object-cover ${className}`} />;
  }

  return (
    <div
      aria-hidden
      className={`flex items-center justify-center bg-slate-100 text-slate-400 ${className}`}
    >
      <svg viewBox="0 0 24 24" fill="none" className="h-1/2 w-1/2" stroke="currentColor" strokeWidth="1.5">
        <circle cx="12" cy="8" r="4" />
        <path d="M4 20c0-4 4-6 8-6s8 2 8 6" strokeLinecap="round" />
      </svg>
    </div>
  );
}
