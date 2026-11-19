/**
 * Builds the brand files the site actually serves, from the vectorised kit in
 * assets/source/brand/ (made by scripts/trace-logo.py from Kay's logo).
 *
 *   node scripts/brand.mjs
 *
 * They go to public/assets/brand/v2/: the .htaccess serves images as
 * immutable for a year, so a new logo needs new addresses, or every returning
 * visitor keeps the old one. The next logo goes to v3/.
 *
 * The PNG icons are rendered from the SVGs by Chromium, not resampled from a
 * bitmap, so every size is drawn sharp from the curves.
 */
import { chromium } from "playwright-core";
import { readFile, writeFile, mkdir } from "node:fs/promises";

const SRC = "assets/source/brand";
const OUT = "public/assets/brand/v2";
const CHROME = process.env.CHROME_PATH ?? "/opt/pw-browsers/chromium-1194/chrome-linux/chrome";

/** SVGs the site serves, copied as they are. */
const SVGS = {
  "logo.svg":            "kay-diving-logo.svg",             // the whole lockup, for the header
  "icon.svg":            "kay-diving-icon.svg",             // the emblem alone, on dark
  "favicon.svg":         "favicon.svg",                     // the emblem on navy, for the tab
  "app-icon.svg":        "app-icon.svg",                    // the emblem on navy, square
  "icon-maskable.svg":   "icon-maskable.svg",               // inside Android's safe zone
};

/** PNGs rendered from those, at the sizes each platform asks for. */
const PNGS = [
  ["apple-touch-icon.png",   "app-icon.svg",      180],
  ["icon-192.png",           "app-icon.svg",      192],
  ["icon-512.png",           "app-icon.svg",      512],
  ["icon-maskable-512.png",  "icon-maskable.svg", 512],
];

/** The .ico the browser tab falls back to. */
const ICO = [16, 32, 48, 64];

await mkdir(OUT, { recursive: true });

for (const [out, src] of Object.entries(SVGS)) {
  const svg = await readFile(`${SRC}/${src}`, "utf8");
  await writeFile(`${OUT}/${out}`, svg);
  console.log(`  ${out.padEnd(22)} <- ${src}`);
}

const browser = await chromium.launch({ executablePath: CHROME, args: ["--no-sandbox"] });
const render = async (svgPath, size) => {
  const svg = await readFile(svgPath, "utf8");
  const page = await browser.newPage({ viewport: { width: size, height: size }, deviceScaleFactor: 1 });
  await page.setContent(
    `<!doctype html><meta charset="utf-8"><style>html,body{margin:0;width:${size}px;height:${size}px}
     svg{width:${size}px;height:${size}px;display:block}</style>${svg}`,
    { waitUntil: "load" });
  const buf = await page.screenshot({ omitBackground: true, type: "png" });
  await page.close();
  return buf;
};

for (const [out, src, size] of PNGS) {
  const buf = await render(`${OUT}/${src}`, size);
  await writeFile(`${OUT}/${out}`, buf);
  console.log(`  ${out.padEnd(22)} ${size}x${size}  ${(buf.length / 1024).toFixed(0)} KB`);
}

/* An .ico is a 6-byte header, one 16-byte entry per image, then the images.
   Since Vista they may be PNGs verbatim, so nothing has to be re-encoded. */
const pngs = [];
for (const size of ICO) pngs.push([size, await render(`${OUT}/favicon.svg`, size)]);
await browser.close();

const header = Buffer.alloc(6);
header.writeUInt16LE(0, 0);            // reserved
header.writeUInt16LE(1, 2);            // 1 = icon
header.writeUInt16LE(pngs.length, 4);
let offset = 6 + 16 * pngs.length;
const dir = [];
for (const [size, buf] of pngs) {
  const e = Buffer.alloc(16);
  e.writeUInt8(size >= 256 ? 0 : size, 0);   // 0 means 256
  e.writeUInt8(size >= 256 ? 0 : size, 1);
  e.writeUInt8(0, 2);                        // palette
  e.writeUInt8(0, 3);                        // reserved
  e.writeUInt16LE(1, 4);                     // colour planes
  e.writeUInt16LE(32, 6);                    // bits per pixel
  e.writeUInt32LE(buf.length, 8);
  e.writeUInt32LE(offset, 12);
  offset += buf.length;
  dir.push(e);
}
const ico = Buffer.concat([header, ...dir, ...pngs.map(([, b]) => b)]);
// Next serves app/favicon.ico at /favicon.ico, so that is where it goes.
await writeFile("app/favicon.ico", ico);
console.log(`  app/favicon.ico        ${ICO.join("/")}  ${(ico.length / 1024).toFixed(0)} KB`);
