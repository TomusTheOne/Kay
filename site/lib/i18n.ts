import { PUBLISHED_LOCALES } from "@/content/products";

/** Every locale the site has copy slots for. */
export const LOCALES = ["en", "es", "fr"] as const;
export type Locale = (typeof LOCALES)[number];
export const DEFAULT_LOCALE: Locale = "en";

/**
 * The locales that are actually built and indexed — the translated ones.
 * Everything that faces a search engine (routes, sitemap, hreflang, the
 * language switcher) reads this, never LOCALES, so an untranslated locale
 * cannot leak out as a duplicate of the English page.
 */
export const LIVE_LOCALES = LOCALES.filter((l) =>
  PUBLISHED_LOCALES.includes(l),
) as readonly Locale[];

export function isLocale(v: string): v is Locale {
  return (LOCALES as readonly string[]).includes(v);
}

/**
 * Open Graph wants a full language_TERRITORY tag, not a bare code — a plain
 * "es" is ignored by the crawlers. Mexico for Spanish, since that is where
 * the shop is and who else reads the page.
 */
export const OG_LOCALE: Record<Locale, string> = {
  en: "en_US",
  es: "es_MX",
  fr: "fr_FR",
};

/**
 * Every locale gets its own prefix. Static export writes en/index.html,
 * es/index.html and fr/index.html; .htaccess sends the bare root to the
 * default one. Trailing slashes throughout, to match trailingSlash: true.
 */
export function pathFor(locale: Locale, path = "/") {
  return path === "/" ? `/${locale}/` : `/${locale}${path}`;
}

/* ---------------------------------------------------------------- shapes --
   Declared rather than inferred from en.json, so a key missing from a
   translation is a compile error instead of "undefined" on a live page.     */

export interface ProductCopy { name: string; tagline: string; text: string; alt: string }
export interface IncludeCopy { name: string; text: string }
export interface Qa { q: string; a: string }
export interface Outcome { tag: string; h2: string; p: string; cta: string }

export interface Dictionary {
  meta: { title: string; description: string };
  nav: Record<"about" | "products" | "included" | "logistics" | "gallery" | "faq"
            | "reserve" | "reserveLong" | "menu" | "language", string>;
  hero: Record<"kicker" | "l1" | "l2" | "lede" | "cta2" | "alt"
             | "water" | "viz" | "from" | "agency" | "agencyLabel", string>;
  gauge: Record<"surface" | "cavern" | "deep", string>;
  about: Record<"tag" | "h2" | "p1" | "p2" | "questionTag" | "question"
              | "plateAlt" | "plateNote", string>;
  products: {
    tag: string; h2: string; intro: string;
    from: string; dive: string; dives: string; perDiver: string;
    depth: string; time: string; where: string; needs: string; halfDay: string;
    sitesTag: string;
    /** Months a seasonal product runs, keyed by product slug. */
    seasonValue: Record<string, string>;
    seasonTag: string;
    level: Record<string, string>;
    places: Record<string, string>;
    items: Record<string, ProductCopy>;
    sites: Record<string, { name: string; depth: string; note: string }>;
  };
  logistics: {
    tag: string; h2: string;
    meetTag: string; meetText: string;
    pickupTag: string;
    pickups: Record<string, { name: string; text: string; price: string }>;
    timesTag: string; timesText: string;
    limitsTag: string; limits: string;
  };
  included: {
    tag: string; h2: string; intro: string;
    items: Record<string, IncludeCopy>;
  };
  gallery: { tag: string; h2: string; intro: string; captions: Record<string, string> };
  quote: { text: string; cite: string };
  book: Record<string, string> & { certs: string[] };
  faq: { tag: string; h2: string; items: Qa[] };
  cta: Record<"kicker" | "h2" | "lede" | "instagram", string>;
  footer: Record<"blurb" | "explore" | "learn" | "find" | "whatsapp" | "email"
                | "place" | "phone" | "meet", string>;
  booking: Record<"thanks" | "pending" | "failed", Outcome>;
  /* Sent by php/lib/notify.php once a deposit clears. The strings live with
     the rest of the copy so a translation stays one file, not two. */
  emails: {
    common: Record<"dive" | "dives" | "halfDay" | "divers" | "diver"
                 | "slotOther" | "noCert", string>;
    diver: Record<string, string>;
    shop: Record<string, string>;
  };
}

const dictionaries: Record<Locale, () => Promise<Dictionary>> = {
  en: () => import("../messages/en.json").then((m) => m.default as unknown as Dictionary),
  es: () => import("../messages/es.json").then((m) => m.default as unknown as Dictionary),
  fr: () => import("../messages/fr.json").then((m) => m.default as unknown as Dictionary),
};

export async function getDictionary(locale: Locale): Promise<Dictionary> {
  return dictionaries[locale]();
}
