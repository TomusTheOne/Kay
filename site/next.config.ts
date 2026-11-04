import type { NextConfig } from "next";
import { readFileSync } from "node:fs";
import { join } from "node:path";

/* The cenote map loads MapLibre from public/vendor/maplibre-gl-<version>/,
   copied there by scripts/vendor.mjs; the page learns the folder from this. */
const maplibre = JSON.parse(
  readFileSync(join(process.cwd(), "node_modules/maplibre-gl/package.json"), "utf8"),
).version as string;

const nextConfig: NextConfig = {
  /* Pure HTML/CSS/JS, uploaded by FTP to OVH shared hosting.
     Headers, redirects and locale routing move to .htaccess — Apache serves
     these files, so Next's own headers() and middleware never run. */
  output: "export",
  trailingSlash: true,
  poweredByHeader: false,
  env: { MAPLIBRE_VERSION: maplibre },
};

export default nextConfig;
