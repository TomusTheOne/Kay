import type { MetadataRoute } from "next";
import { LIVE_LOCALES, DEFAULT_LOCALE, pathFor, type Locale } from "@/lib/i18n";
import { SHOP, CONTENT_UPDATED } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED, GUIDE_UPDATED, cenoteBySlug } from "@/content/cenotes";

/**
 * One entry per page and locale, each declaring its siblings so Google pairs
 * them. The cenote guide joins only once it is published: until then its
 * pages are noindex, and listing a noindex page here would contradict it.
 *
 * Each entry lists the images the page shows, so a photograph or a dive map
 * can be found in image search with the page it belongs to.
 */
function images(path: string, locale: Locale): string[] {
  const abs = (p: string) => `${SHOP.domain}${p}`;
  if (path === "/") return [abs("/assets/photos/hero.webp"), abs(`/assets/og/${locale}.jpg`)];
  const slug = path.match(/^\/cenotes\/([^/]+)\/$/)?.[1];
  const c = slug ? cenoteBySlug(slug) : undefined;
  if (!c) return [];
  return [
    ...(c.photo ? [abs(`/assets/photos/${c.photo}.webp`)] : []),
    ...(c.plan ? [abs(`/assets/maps/${c.slug}-${locale}-${c.plan.w * 2}.webp`)] : []),
  ];
}

export default function sitemap(): MetadataRoute.Sitemap {
  const paths = [
    "/",
    ...(GUIDE_PUBLISHED ? ["/cenotes/", ...CENOTES.map((c) => `/cenotes/${c.slug}/`)] : []),
  ];

  return paths.flatMap((path) => {
    const languages = {
      ...Object.fromEntries(LIVE_LOCALES.map((l) => [l, `${SHOP.domain}${pathFor(l, path)}`])),
      "x-default": `${SHOP.domain}${pathFor(DEFAULT_LOCALE, path)}`,
    };
    return LIVE_LOCALES.map((locale) => ({
      url: `${SHOP.domain}${pathFor(locale, path)}`,
      lastModified: new Date(path === "/" ? CONTENT_UPDATED : GUIDE_UPDATED),
      changeFrequency: "monthly" as const,
      priority: path === "/" ? (locale === "en" ? 1 : 0.8) : 0.6,
      alternates: { languages },
      images: images(path, locale),
    }));
  });
}

export const dynamic = "force-static";
