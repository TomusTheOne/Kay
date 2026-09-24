import type { MetadataRoute } from "next";
import { LIVE_LOCALES, pathFor } from "@/lib/i18n";
import { SHOP, CONTENT_UPDATED } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED } from "@/content/cenotes";

/**
 * One entry per page and locale, each declaring its siblings so Google pairs
 * them. The cenote guide joins only once it is published: until then its
 * pages are noindex, and listing a noindex page here would contradict it.
 */
export default function sitemap(): MetadataRoute.Sitemap {
  const paths = [
    "/",
    ...(GUIDE_PUBLISHED ? ["/cenotes/", ...CENOTES.map((c) => `/cenotes/${c.slug}/`)] : []),
  ];

  return paths.flatMap((path) => {
    const languages = Object.fromEntries(
      LIVE_LOCALES.map((l) => [l, `${SHOP.domain}${pathFor(l, path)}`]),
    );
    return LIVE_LOCALES.map((locale) => ({
      url: `${SHOP.domain}${pathFor(locale, path)}`,
      lastModified: new Date(CONTENT_UPDATED),
      changeFrequency: "monthly" as const,
      priority: path === "/" ? (locale === "en" ? 1 : 0.8) : 0.6,
      alternates: { languages },
    }));
  });
}

export const dynamic = "force-static";
