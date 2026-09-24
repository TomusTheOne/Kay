/**
 * Copies MapLibre's ES modules into public/vendor/, where the cenote map
 * loads them from at run time instead of through the bundler.
 *
 * MapLibre 6 starts its tile worker from a file beside its own module
 * (new URL("./maplibre-gl-worker.mjs", import.meta.url)). Bundled, that URL
 * points into _next/static/ where no such file exists and the map stays
 * blank. Served as-is, the page and the worker also share one copy of
 * maplibre-gl-shared.mjs — the bulk of the library — instead of downloading
 * it twice. The folder carries the version, so it can be cached for a year.
 *
 * Runs before `next dev` and `next build` (predev/prebuild in package.json).
 * public/vendor/ is not committed: node_modules is the source.
 */
import { mkdir, readFile, readdir, rm, writeFile } from "node:fs/promises";

const { version } = JSON.parse(await readFile("node_modules/maplibre-gl/package.json", "utf8"));
const dir = `public/vendor/maplibre-gl-${version}`;

await mkdir("public/vendor", { recursive: true });
for (const old of await readdir("public/vendor")) {
  if (old.startsWith("maplibre-gl-") && `public/vendor/${old}` !== dir) {
    await rm(`public/vendor/${old}`, { recursive: true, force: true });
  }
}
await mkdir(dir, { recursive: true });
for (const f of ["maplibre-gl.mjs", "maplibre-gl-shared.mjs", "maplibre-gl-worker.mjs"]) {
  const src = await readFile(`node_modules/maplibre-gl/dist/${f}`, "utf8");
  // The source maps stay behind; a reference to one would 404 in every devtools.
  await writeFile(`${dir}/${f}`, src.replace(/\n\/\/# sourceMappingURL=\S+\s*$/, "\n"));
}
console.log(`MapLibre ${version} → ${dir}/`);
