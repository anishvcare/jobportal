import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Emit a self-contained server in .next/standalone (server.js + only the
  // node_modules it needs). This lets hosts that cannot build Next.js
  // themselves (e.g. cPanel/CloudLinux shared hosting with old glibc and
  // process limits) run a site that was built elsewhere, with plain
  // `node server.js` and no `npm install`.
  // See .github/workflows/deploy-frontend.yml.
  output: "standalone",
  // The app does not use next/image, so sharp's native image libraries
  // (~47 MB) are not needed at runtime. Excluding them keeps the deploy
  // bundle small. Remove this if next/image optimisation is ever added.
  outputFileTracingExcludes: {
    "*": ["node_modules/@img/**", "node_modules/sharp/**"],
  },

  async headers() {
    return [
      {
        // The service worker must never be served from the browser HTTP cache,
        // so an updated SW is always picked up. Also pin its content type.
        source: "/sw.js",
        headers: [
          {
            key: "Cache-Control",
            value: "no-cache, no-store, must-revalidate",
          },
          {
            key: "Content-Type",
            value: "application/javascript; charset=utf-8",
          },
        ],
      },
    ];
  },
};

export default nextConfig;
