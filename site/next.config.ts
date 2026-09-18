import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /* Pure HTML/CSS/JS, uploaded by FTP to OVH shared hosting.
     Headers, redirects and locale routing move to .htaccess — Apache serves
     these files, so Next's own headers() and middleware never run. */
  output: "export",
  trailingSlash: true,
  poweredByHeader: false,
};

export default nextConfig;
