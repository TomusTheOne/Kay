/**
 * Assembles exactly what gets uploaded to OVH's www/ directory:
 *
 *   out/            the static export         → www/
 *   php/            the endpoints + send-live → www/api/
 *   php/admin/      the admin space           → www/admin/
 *   public-htaccess the Apache configuration  → www/.htaccess
 *
 * products.json and messages/ are copied from the source of truth, so the PHP
 * reads the same catalogue and the same copy the site was built from — a
 * confirmation email cannot name a product differently from the page that
 * sold it. The test runner is left behind.
 */
import { cp, rm, mkdir, readdir, readFile, writeFile } from "node:fs/promises";
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

/* The cenote guide: every cenote needs Kay's text in every published
   language, products that exist, and a position the basemap covers — and the
   basemap itself must be in the bundle, or the map loads onto nothing. */
{
  const guide = JSON.parse(await readFile("content/cenotes.json", "utf8"));
  const { products } = JSON.parse(await readFile("content/products.json", "utf8"));
  const [west, south, east, north] = guide.bounds;
  const problems = [];
  if (!existsSync(`${OUT}${guide.basemap}`)) problems.push(`the basemap ${guide.basemap} is not in out/ — run npm run basemap`);
  for (const locale of publishedLocales) {
    const items = JSON.parse(await readFile(`messages/${locale}.json`, "utf8")).cenotes?.items ?? {};
    for (const c of guide.cenotes) if (!items[c.slug]) problems.push(`${c.slug} has no text in messages/${locale}.json`);
  }
  for (const c of guide.cenotes) {
    if (!(c.lon > west && c.lon < east && c.lat > south && c.lat < north)) problems.push(`${c.slug} lies outside the basemap`);
    for (const p of c.products) if (!products.some((x) => x.slug === p)) problems.push(`${c.slug} names an unknown product "${p}"`);
  }
  if (problems.length) {
    console.error("content/cenotes.json:\n  " + problems.join("\n  "));
    process.exit(1);
  }
  console.log(`Cenote guide: ${guide.cenotes.length} cenotes, ${guide.published ? "PUBLISHED" : "preview only (noindex, unlisted)"}`);
}

await rm(`${OUT}/api`, { recursive: true, force: true });
await mkdir(`${OUT}/api/lib`, { recursive: true });

// send-live.php goes with them: it refuses to run over HTTP, and a way to
// prove a confirmation reaches an inbox belongs on the host that sends it.
// track.php is the traffic beacon every page posts to; availability.php
// tells the booking form which days Kay closed.
// gear.php takes the equipment sizes divers send from their confirmation link.
for (const f of ["booking.php", "webhook.php", "send-live.php", "track.php", "availability.php", "gear.php"]) {
  await cp(`php/${f}`, `${OUT}/api/${f}`);
}
for (const f of await readdir("php/lib")) {
  await cp(`php/lib/${f}`, `${OUT}/api/lib/${f}`);
}
// The baseline schema: migration 1 reads it, so a fresh database needs
// nothing but the admin's setup page. .htaccess refuses to serve any .sql.
await cp("php/schema.sql", `${OUT}/api/schema.sql`);

// The admin space, at /admin/. Its pages load the same library as the
// endpoints, from api/lib/ — see php/admin/_boot.php.
await rm(`${OUT}/admin`, { recursive: true, force: true });
await mkdir(`${OUT}/admin`, { recursive: true });
for (const f of await readdir("php/admin")) {
  await cp(`php/admin/${f}`, `${OUT}/admin/${f}`);
}
// One catalogue and one set of strings, read by the build and by the endpoints.
await cp("content/products.json", `${OUT}/api/products.json`);
await mkdir(`${OUT}/api/messages`, { recursive: true });
for (const f of await readdir("messages")) {
  await cp(`messages/${f}`, `${OUT}/api/messages/${f}`);
}
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

/* Apache redirects to the same host the pages call canonical. Taking it from
   the built sitemap rather than a second setting means the two cannot drift. */
const host = new URL(loc).host;
const htaccess = await readFile("public-htaccess", "utf8");
if (!htaccess.includes("__CANONICAL_HOST__") || !htaccess.includes("__CANONICAL_HOST_RE__")) {
  console.error("public-htaccess no longer carries the canonical-host placeholders.");
  process.exit(1);
}
await writeFile(`${OUT}/.htaccess`, htaccess
  // The RewriteCond is a regex: an unescaped dot would also match kaydivingXcom.
  .replaceAll("__CANONICAL_HOST_RE__", host.replace(/[.+*?^$()[\]{}|\\]/g, "\\$&"))
  .replaceAll("__CANONICAL_HOST__", host));

console.log(`Canonical host: ${host} — every other hostname 301s to it`);

console.log("Deploy bundle ready in out/ — upload its contents to www/");
console.log("Remember: kay-config.php belongs ABOVE www/, never inside it.");
