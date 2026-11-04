import type { Metadata } from "next";
import { LIVE_LOCALES, DEFAULT_LOCALE, OG_LOCALE, getDictionary, pathFor, type Locale } from "@/lib/i18n";
import { SHOP, EXPERIENCE_PAGES } from "@/content/products";

/**
 * The experience pages — one per thing Kay sells, as opposed to a card on the
 * home page. Their copy is written one language at a time, so everything here
 * asks which locales actually have a given page rather than assuming three.
 */

/** The locales whose copy for this experience is written. */
export async function localesWithExperience(slug: string): Promise<Locale[]> {
  const out: Locale[] = [];
  for (const locale of LIVE_LOCALES) {
    const t = await getDictionary(locale);
    if (t.exp.items[slug]) out.push(locale);
  }
  return out;
}

/** The experiences this locale has copy for, in catalogue order. */
export async function experiencesFor(locale: Locale): Promise<string[]> {
  const t = await getDictionary(locale);
  return EXPERIENCE_PAGES.filter((slug) => t.exp.items[slug]);
}

/**
 * hreflang over the locales that really have the page — never all three.
 * Declaring a translation that does not exist points Google at a 404 and
 * invalidates the whole cluster, which is worse than declaring nothing.
 * x-default goes to the default locale when it has the page, and otherwise
 * to the first one that does, so the tag is never a dangling link either.
 */
export async function expMetadata(
  locale: Locale, slug: string, title: string, description: string,
): Promise<Metadata> {
  const path = `/${slug}/`;
  const url = `${SHOP.domain}${pathFor(locale, path)}`;
  const live = await localesWithExperience(slug);
  const fallback = live.includes(DEFAULT_LOCALE) ? DEFAULT_LOCALE : live[0];

  return {
    title, description,
    alternates: {
      canonical: url,
      languages: {
        ...Object.fromEntries(live.map((l) => [l, `${SHOP.domain}${pathFor(l, path)}`])),
        ...(fallback ? { "x-default": `${SHOP.domain}${pathFor(fallback, path)}` } : {}),
      },
    },
    openGraph: {
      type: "website", siteName: "Kay Diving Tulum", url, title, description,
      locale: OG_LOCALE[locale],
      images: [{ url: `/assets/og/${locale}.jpg`, width: 1200, height: 630, type: "image/jpeg" }],
    },
  };
}
