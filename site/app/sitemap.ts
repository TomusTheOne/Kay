import type { MetadataRoute } from "next";
import { LIVE_LOCALES, pathFor } from "@/lib/i18n";
import { SHOP, CONTENT_UPDATED } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED } from "@/content/cenotes";
import { EXPERIENCE_PAGES } from "@/content/products";
import { localesWithExperience } from "@/lib/exp";

/**
 * One entry per page and locale, each declaring its siblings so Google pairs
 * them. The cenote guide joins only once it is published: until then its
 * pages are noindex, and listing a noindex page here would contradict it.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const paths = [
    "/",
    ...(GUIDE_PUBLISHED ? ["/cenotes/", ...CENOTES.map((c) => `/cenotes/${c.slug}/`)] : []),
  ];

  const rows: MetadataRoute.Sitemap = paths.flatMap((path) => {
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

  /* The experience pages, each listing only the locales whose copy is
     written. A sitemap entry for a page that does not exist is a 404 handed
     to Google on a plate, and an hreflang pointing at one invalidates the
     cluster it belongs to. */
  for (const slug of EXPERIENCE_PAGES) {
    const path = `/${slug}/`;
    const live = await localesWithExperience(slug);
    const languages = Object.fromEntries(
      live.map((l) => [l, `${SHOP.domain}${pathFor(l, path)}`]),
    );
    rows.push(...live.map((locale) => ({
      url: `${SHOP.domain}${pathFor(locale, path)}`,
      lastModified: new Date(CONTENT_UPDATED),
      changeFrequency: "monthly" as const,
      priority: 0.8,
      alternates: { languages },
    })));
  }

  return rows;
}

export const dynamic = "force-static";
