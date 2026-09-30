// generate-icons.mjs
//
// Generates the Nexus Flow PWA icon set as valid PNGs using `sharp`.
//
// Run with:
//   cd frontend && node scripts/generate-icons.mjs
//
// Design spec:
//   - Solid teal background: #0f766e (brand-700).
//   - Centered white "NF" wordmark.
//   - Maskable icon is full-bleed (square, edge-to-edge opaque teal) and keeps
//     ~20% padding clear on every edge so the mark is never clipped by
//     circular / rounded platform masks. The platform applies its own mask, so
//     the icon itself must have no transparent corners.
//   - apple-touch-icon is fully opaque (no transparency) per iOS requirements.
//
// Outputs (frontend/public/icons/):
//   icon-192.png         192x192   purpose "any"
//   icon-512.png         512x512   purpose "any"
//   maskable-512.png     512x512   purpose "maskable" (safe-zone padded)
//   apple-touch-icon.png 180x180   opaque, iOS home screen
//   favicon-32.png        32x32    favicon
//
// The script is idempotent: re-running produces identically sized icons.

import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { mkdir } from "node:fs/promises";
import sharp from "sharp";

const TEAL = "#0f766e";
const WHITE = "#ffffff";

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = join(__dirname, "..", "public", "icons");

/**
 * Build an SVG for the icon.
 * @param {number} size overall square size in px
 * @param {number} markScale fraction of the size the wordmark box occupies (1 = full bleed)
 * @param {number|null} radius optional corner radius for the background rect (null = square)
 */
function iconSvg(size, markScale, radius = null) {
  // Font size is derived from the area allocated to the mark so the maskable
  // variant naturally shrinks the wordmark into the safe zone.
  const fontSize = Math.round(size * 0.42 * markScale);
  const rx = radius == null ? 0 : radius;
  return `<?xml version="1.0" encoding="UTF-8"?>
<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" xmlns="http://www.w3.org/2000/svg">
  <rect x="0" y="0" width="${size}" height="${size}" rx="${rx}" ry="${rx}" fill="${TEAL}"/>
  <text x="50%" y="50%" fill="${WHITE}" font-family="Helvetica, Arial, sans-serif"
        font-weight="700" font-size="${fontSize}" text-anchor="middle" dominant-baseline="central"
        letter-spacing="${Math.round(size * 0.01)}">NF</text>
</svg>`;
}

/**
 * Render an SVG string to a PNG at an exact size.
 * `flatten` forces a fully opaque background (used for the iOS icon).
 */
async function renderPng(svg, size, outPath, { flatten = false } = {}) {
  let pipeline = sharp(Buffer.from(svg)).resize(size, size);
  if (flatten) {
    pipeline = pipeline.flatten({ background: TEAL });
  }
  await pipeline.png().toFile(outPath);
  const meta = await sharp(outPath).metadata();
  console.log(`wrote ${outPath}  ${meta.width}x${meta.height}  ${meta.format}`);
}

async function main() {
  await mkdir(OUT_DIR, { recursive: true });

  // Standard "any" icons: mark fills most of the tile.
  await renderPng(iconSvg(192, 1), 192, join(OUT_DIR, "icon-192.png"));
  await renderPng(iconSvg(512, 1), 512, join(OUT_DIR, "icon-512.png"));

  // Maskable icon: full-bleed square background (radius 0) so the corners are
  // opaque teal and the platform mask defines the silhouette. Keep ~20%
  // padding clear on every edge, so the wordmark sits inside the central 60%
  // safe zone.
  await renderPng(iconSvg(512, 0.6, 0), 512, join(OUT_DIR, "maskable-512.png"));

  // iOS apple-touch-icon: opaque teal background, no transparency.
  await renderPng(iconSvg(180, 1), 180, join(OUT_DIR, "apple-touch-icon.png"), {
    flatten: true,
  });

  // Favicon PNG.
  await renderPng(iconSvg(32, 1), 32, join(OUT_DIR, "favicon-32.png"));
}

main().catch((err) => {
  console.error(err);
  process.exitCode = 1;
});
