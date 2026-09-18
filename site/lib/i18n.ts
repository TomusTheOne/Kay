export const LOCALES = ["en", "es", "fr"] as const;
export type Locale = (typeof LOCALES)[number];
export const DEFAULT_LOCALE: Locale = "en";

export function isLocale(v: string): v is Locale {
  return (LOCALES as readonly string[]).includes(v);
}

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
  nav: Record<"about" | "products" | "included" | "gallery" | "faq"
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
    level: Record<string, string>;
    places: Record<string, string>;
    items: Record<string, ProductCopy>;
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
  footer: Record<"blurb" | "explore" | "learn" | "find" | "whatsapp" | "email" | "place", string>;
  booking: Record<"thanks" | "pending" | "failed", Outcome>;
}

const dictionaries: Record<Locale, () => Promise<Dictionary>> = {
  en: () => import("../messages/en.json").then((m) => m.default as unknown as Dictionary),
  es: () => import("../messages/es.json").then((m) => m.default as unknown as Dictionary),
  fr: () => import("../messages/fr.json").then((m) => m.default as unknown as Dictionary),
};

export async function getDictionary(locale: Locale): Promise<Dictionary> {
  return dictionaries[locale]();
}
