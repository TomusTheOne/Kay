/**
 * Assembles exactly what gets uploaded to OVH's www/ directory:
 *
 *   out/            the static export         → www/
 *   php/            the two endpoints         → www/api/
 *   public-htaccess the Apache configuration  → www/.htaccess
 *
 * products.json and messages/ are copied from the source of truth, so the PHP
 * reads the same catalogue and the same copy the site was built from — a
 * confirmation email cannot name a product differently from the page that
 * sold it. The test runner is left behind.
 */
import { cp, rm, mkdir, readdir, readFile } from "node:fs/promises";
import { existsSync } from "node:fs";

const OUT = "out";
if (!existsSync(OUT)) {
  console.error("Run `npm run build` first — out/ does not exist.");
  process.exit(1);
}

/* A locale cannot be declared published while its messages are still the
   English originals: that would ship three URLs of identical text, each
   claiming a different hreflang, which costs ranking rather than earning it. */
const { publishedLocales } = JSON.parse(await readFile("content/products.json", "utf8"));
for (const locale of publishedLocales) {
  const messages = JSON.parse(await readFile(`messages/${locale}.json`, "utf8"));
  if (messages._translated === false) {
    console.error(
      `messages/${locale}.json is still flagged _translated: false, but "${locale}" ` +
      `is listed in publishedLocales. Translate it, or drop it from the list.`,
    );
    process.exit(1);
  }
}

await rm(`${OUT}/api`, { recursive: true, force: true });
await mkdir(`${OUT}/api/lib`, { recursive: true });

for (const f of ["booking.php", "webhook.php"]) {
  await cp(`php/${f}`, `${OUT}/api/${f}`);
}
for (const f of await readdir("php/lib")) {
  await cp(`php/lib/${f}`, `${OUT}/api/lib/${f}`);
}
// One catalogue and one set of strings, read by the build and by the endpoints.
await cp("content/products.json", `${OUT}/api/products.json`);
await mkdir(`${OUT}/api/messages`, { recursive: true });
for (const f of await readdir("messages")) {
  await cp(`messages/${f}`, `${OUT}/api/messages/${f}`);
}
await cp("public-htaccess", `${OUT}/.htaccess`);

/* Last gate before upload: every URL a crawler reads must be absolute. This
   catches a mistyped or missing NEXT_PUBLIC_SITE_URL, which would otherwise
   ship a sitemap and canonicals pointing nowhere — and nothing would look
   broken to a human. */
const sitemap = await readFile(`${OUT}/sitemap.xml`, "utf8");
const loc = sitemap.match(/<loc>([^<]+)<\/loc>/)?.[1] ?? "";
if (!/^https:\/\/[^/]+\./.test(loc)) {
  console.error(`sitemap.xml declares "${loc}" — NEXT_PUBLIC_SITE_URL is missing or wrong.`);
  process.exit(1);
}
console.log(`Canonical host: ${new URL(loc).origin}`);

console.log("Deploy bundle ready in out/ — upload its contents to www/");
console.log("Remember: kay-config.php belongs ABOVE www/, never inside it.");
