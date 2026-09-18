/**
 * Assembles exactly what gets uploaded to OVH's www/ directory:
 *
 *   out/            the static export         → www/
 *   php/            the two endpoints         → www/api/
 *   public-htaccess the Apache configuration  → www/.htaccess
 *
 * products.json is copied from content/ so the PHP reads the same catalogue
 * the site was built from, and the test runner is left behind.
 */
import { cp, rm, mkdir, readdir } from "node:fs/promises";
import { existsSync } from "node:fs";

const OUT = "out";
if (!existsSync(OUT)) {
  console.error("Run `npm run build` first — out/ does not exist.");
  process.exit(1);
}

await rm(`${OUT}/api`, { recursive: true, force: true });
await mkdir(`${OUT}/api/lib`, { recursive: true });

for (const f of ["booking.php", "webhook.php"]) {
  await cp(`php/${f}`, `${OUT}/api/${f}`);
}
for (const f of await readdir("php/lib")) {
  await cp(`php/lib/${f}`, `${OUT}/api/lib/${f}`);
}
// One catalogue, read by the build and by the endpoints.
await cp("content/products.json", `${OUT}/api/products.json`);
await cp("public-htaccess", `${OUT}/.htaccess`);

console.log("Deploy bundle ready in out/ — upload its contents to www/");
console.log("Remember: kay-config.php belongs ABOVE www/, never inside it.");
