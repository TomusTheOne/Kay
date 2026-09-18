/**
 * Renders the Open Graph card, one per locale, into public/assets/og/.
 *
 * It exists because the hero photo is 861×531 WebP, and that is the wrong
 * thing to hand a link preview: Facebook and LinkedIn want at least
 * 1200×630, and WhatsApp — where a dive shop's links actually travel — is
 * unreliable with WebP. So the card is a real 1200×630 JPEG, built from the
 * site's own fonts and words so a shared link looks like the site.
 *
 *   node scripts/og.mjs        (needs the Chromium at PLAYWRIGHT_BROWSERS_PATH)
 */
import { chromium } from "playwright-core";
import { mkdir, writeFile, readFile } from "node:fs/promises";
import { existsSync } from "node:fs";

const LOCALES = ["en", "es", "fr"];
const OUT = "public/assets/og";
const CHROME = process.env.CHROME_PATH ?? "/opt/pw-browsers/chromium-1194/chrome-linux/chrome";

const products = JSON.parse(await readFile("content/products.json", "utf8"));
const cheapest = Math.min(
  ...products.products.flatMap((p) => p.options.map((o) => o.price)),
);
const heroDataUri =
  "data:image/webp;base64," +
  (await readFile("public/assets/photos/hero.webp")).toString("base64");
// The emblem, two-tone, inlined so the card renders without a network fetch.
const markDataUri =
  "data:image/svg+xml;base64," +
  (await readFile("public/assets/brand/icon.svg")).toString("base64");

/** The card, at exactly the size the crawlers ask for. */
const card = (t) => `<!doctype html><html><head><meta charset="utf-8">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{width:1200px;height:630px;overflow:hidden;background:#03090E;position:relative;
       font-family:'IBM Plex Mono',monospace;color:#E6FBF6}
  .shot{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  /* The photo carries the mood; the ink carries the words. */
  .veil{position:absolute;inset:0;background:
      linear-gradient(180deg,rgba(3,9,14,.55) 0%,rgba(3,9,14,.12) 32%,rgba(3,9,14,.88) 78%,#03090E 100%)}
  .frame{position:absolute;inset:34px;border:1px solid rgba(169,245,236,.18);border-radius:10px}
  .pad{position:absolute;inset:74px;display:flex;flex-direction:column;justify-content:space-between}
  .mark{display:flex;align-items:center;gap:16px}
  .dot{width:66px;height:66px;flex:none;background:url('${markDataUri}') center/contain no-repeat}
  .name{font-family:'Instrument Serif',serif;font-size:34px;letter-spacing:.01em;line-height:1}
  .place{font-size:12px;letter-spacing:.22em;color:#4FE0D2;margin-top:5px}
  h1{font-family:'Instrument Serif',serif;font-weight:400;font-size:74px;line-height:1.02;
     letter-spacing:-.01em;max-width:15ch}
  h1 em{font-style:italic;color:#A9F5EC}
  .strip{display:flex;gap:26px;align-items:center;font-size:14px;letter-spacing:.16em;
         color:rgba(230,251,246,.72);border-top:1px solid rgba(169,245,236,.16);padding-top:20px}
  .strip b{color:#E6FBF6;font-weight:500}
  .sep{color:rgba(79,224,210,.6)}
</style></head><body>
  <img class="shot" src="${heroDataUri}" alt="">
  <div class="veil"></div><div class="frame"></div>
  <div class="pad">
    <div class="mark"><span class="dot"></span><div>
      <div class="name">Kay Diving</div>
      <div class="place">TULUM · QUINTANA ROO · MÉXICO</div>
    </div></div>
    <div>
      <h1>${t.l1}<br><em>${t.l2}</em></h1>
      <div class="strip" style="margin-top:34px">
        <span>${t.agency} <b>${products.shop.agency}</b></span><span class="sep">•</span>
        <span>${t.depth} <b>${products.maxDepthM} M</b></span><span class="sep">•</span>
        <span>${t.from} <b>$${cheapest}</b></span>
      </div>
    </div>
  </div>
</body></html>`;

if (!existsSync(CHROME)) {
  console.error(`Chromium not found at ${CHROME} — set CHROME_PATH.`);
  process.exit(1);
}
await mkdir(OUT, { recursive: true });
const browser = await chromium.launch({ executablePath: CHROME, args: ["--no-sandbox"] });

for (const locale of LOCALES) {
  const m = JSON.parse(await readFile(`messages/${locale}.json`, "utf8"));
  const html = card({
    // The hero headline, already translated; <br> is markup in the source.
    l1: m.hero.l1.replace(/<br\s*\/?>/gi, " "),
    l2: m.hero.l2.replace(/<br\s*\/?>/gi, " "),
    agency: m.hero.agencyLabel.toUpperCase(),
    depth: m.products.depth.toUpperCase(),
    from: m.products.from.toUpperCase(),
  });
  const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
  await page.setContent(html, { waitUntil: "networkidle" });
  await page.evaluate(() => document.fonts.ready);
  // JPEG, not WebP: WhatsApp previews are unreliable with WebP.
  const buf = await page.screenshot({ type: "jpeg", quality: 88 });
  await writeFile(`${OUT}/${locale}.jpg`, buf);
  await page.close();
  console.log(`${OUT}/${locale}.jpg  1200×630  ${(buf.length / 1024).toFixed(0)} KB`);
}

await browser.close();
