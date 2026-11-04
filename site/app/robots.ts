import type { MetadataRoute } from "next";
import { SHOP } from "@/content/products";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [{
      userAgent: "*",
      allow: "/",
      // Only the endpoints. The payment outcome pages are NOT listed here on
      // purpose: they already carry <meta robots="noindex">, and a path
      // blocked in robots.txt is never fetched, so that noindex would never
      // be read. Blocking and noindexing the same URL cancels the noindex.
      disallow: ["/api/"],
    }],
    sitemap: `${SHOP.domain}/sitemap.xml`,
    host: SHOP.domain,
  };
}

export const dynamic = "force-static";
