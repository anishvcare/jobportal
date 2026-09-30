import type { MetadataRoute } from "next";
import { APP_NAME } from "@/lib/config";

// Web app manifest for the Nexus Flow PWA. Next.js auto-links this from
// src/app/manifest.ts and serves it at /manifest.webmanifest — do not add a
// manual <link rel="manifest">. Keep start_url and scope at "/" so the Google
// OAuth round-trip is not trapped by query params.
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: APP_NAME,
    short_name: APP_NAME,
    description:
      "Find jobs in Kerala, across India, in Russia and worldwide. Build your profile, upload your documents once and apply in minutes.",
    id: "/",
    lang: "en",
    start_url: "/",
    scope: "/",
    display: "standalone",
    theme_color: "#0f766e",
    background_color: "#f0fdfa",
    icons: [
      {
        src: "/icons/icon-192.png",
        sizes: "192x192",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/icons/icon-512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/icons/maskable-512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "maskable",
      },
    ],
  };
}
