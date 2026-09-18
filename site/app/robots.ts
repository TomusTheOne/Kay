import type { MetadataRoute } from "next";
import { SHOP } from "@/content/products";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [{
      userAgent: "*",
      allow: "/",
      // Payment outcome pages carry no content and must never be indexed.
      disallow: ["/api/", "/en/booking/", "/es/booking/", "/fr/booking/"],
    }],
    sitemap: `${SHOP.domain}/sitemap.xml`,
    host: SHOP.domain,
  };
}

export const dynamic = "force-static";
