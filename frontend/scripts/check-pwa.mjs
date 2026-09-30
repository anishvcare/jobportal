// check-pwa.mjs
//
// Lightweight PWA sanity check. Asserts that:
//   1. src/app/manifest.ts exists (Next.js serves it at /manifest.webmanifest).
//   2. Every expected icon file exists under public/icons/ at its declared size
//      (verified with `sharp` metadata, not just file presence).
//
// Exits with code 0 when everything matches, non-zero on any mismatch.
// This is a standalone check and is intentionally NOT wired into npm scripts.
//
// Run with:
//   cd frontend && node scripts/check-pwa.mjs

import { fileURLToPath } from "node:url";
import { dirname, join, relative } from "node:path";
import { access } from "node:fs/promises";
import sharp from "sharp";

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, "..");
const ICONS_DIR = join(ROOT, "public", "icons");

// Declared icon sizes must match manifest.ts and layout.tsx metadata.
const EXPECTED_ICONS = [
  { file: "icon-192.png", width: 192, height: 192 },
  { file: "icon-512.png", width: 512, height: 512 },
  { file: "maskable-512.png", width: 512, height: 512 },
  { file: "apple-touch-icon.png", width: 180, height: 180 },
  { file: "favicon-32.png", width: 32, height: 32 },
];

const errors = [];

async function checkManifest() {
  const manifestPath = join(ROOT, "src", "app", "manifest.ts");
  try {
    await access(manifestPath);
    console.log(`ok   ${relative(ROOT, manifestPath)}`);
  } catch {
    errors.push(`missing ${relative(ROOT, manifestPath)}`);
  }
}

async function checkIcons() {
  for (const { file, width, height } of EXPECTED_ICONS) {
    const iconPath = join(ICONS_DIR, file);
    try {
      const meta = await sharp(iconPath).metadata();
      if (meta.width !== width || meta.height !== height) {
        errors.push(`size mismatch ${file}: expected ${width}x${height}, got ${meta.width}x${meta.height}`);
      } else {
        console.log(`ok   icons/${file}  ${meta.width}x${meta.height}  ${meta.format}`);
      }
    } catch {
      errors.push(`missing or unreadable icons/${file}`);
    }
  }
}

async function main() {
  await checkManifest();
  await checkIcons();

  if (errors.length > 0) {
    console.error("\nPWA check FAILED:");
    for (const err of errors) console.error(`  - ${err}`);
    process.exit(1);
  }
  console.log("\nPWA check passed.");
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
