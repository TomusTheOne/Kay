/* Screenshot each labelled map page listed in .build/jobs.json at 2x. Playwright
   is loaded the CommonJS way so a global install found through NODE_PATH works. */
import { createRequire } from "node:module";
import { readFileSync } from "node:fs";

const load = createRequire(import.meta.url);
const { chromium } = load("playwright");
const jobs = JSON.parse(readFileSync(process.argv[2], "utf8"));

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
for (const [html, png, w, h] of jobs) {
  const page = await browser.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: 2 });
  await page.goto(`file://${html}`, { waitUntil: "networkidle" });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(200);
  await page.screenshot({ path: png });
  await page.close();
  console.log(png);
}
await browser.close();
