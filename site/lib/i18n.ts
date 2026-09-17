export const LOCALES = ["en", "es", "fr"] as const;
export type Locale = (typeof LOCALES)[number];
export const DEFAULT_LOCALE: Locale = "en";

export function isLocale(v: string): v is Locale {
  return (LOCALES as readonly string[]).includes(v);
}

/** Only the default locale sits at the root; the others get a prefix. */
export function pathFor(locale: Locale, path = "/") {
  return locale === DEFAULT_LOCALE ? path : `/${locale}${path === "/" ? "" : path}`;
}

/* ---------------------------------------------------------------- shapes --
   Declared rather than inferred from en.json, so a key missing from a
   translation is a compile error instead of "undefined" on a live page.     */

export interface DiveCopy { name: string; text: string; alt: string }
export interface CourseCopy { level: string; name: string; text: string }
export interface Qa { q: string; a: string }

export interface Dictionary {
  meta: { title: string; description: string };
  nav: Record<"underworld" | "dives" | "reef" | "courses" | "gallery" | "faq"
            | "reserve" | "reserveLong" | "menu" | "language", string>;
  hero: Record<"kicker" | "l1" | "l2" | "lede" | "cta2" | "alt"
             | "water" | "viz" | "sacActun" | "max" | "divers", string>;
  gauge: Record<"surface" | "cavern" | "deep" | "abyss", string>;
  underworld: Record<"tag" | "h2" | "p1" | "p2" | "plateAlt" | "plateNote"
                   | "stat1" | "stat2" | "stat3" | "stat4", string>;
  dives: {
    tag: string; h2: string; intro: string; from: string; tanksLabel: string;
    level: Record<string, string>;
    items: Record<string, DiveCopy>;
  };
  reef: Record<"tag" | "h2" | "lede" | "t1" | "t2" | "t3" | "t4" | "cta", string>;
  courses: {
    tag: string; h2: string; intro: string; days: string; day: string;
    items: Record<string, CourseCopy>;
  };
  gallery: { tag: string; h2: string; intro: string; captions: Record<string, string> };
  quote: { text: string; cite: string };
  book: Record<string, string> & {
    certs: string[];
    pickup: Record<string, string>;
    addons: Record<string, string>;
  };
  faq: { tag: string; h2: string; items: Qa[] };
  cta: Record<"kicker" | "h2" | "lede" | "instagram", string>;
  footer: Record<"blurb" | "dive" | "cenotes" | "find" | "whatsapp" | "email" | "place", string>;
  booking: Record<"thanks" | "pending" | "failed",
                  Record<"tag" | "h2" | "p" | "cta", string>>;
}

const dictionaries: Record<Locale, () => Promise<Dictionary>> = {
  en: () => import("../messages/en.json").then((m) => m.default as unknown as Dictionary),
  es: () => import("../messages/es.json").then((m) => m.default as unknown as Dictionary),
  fr: () => import("../messages/fr.json").then((m) => m.default as unknown as Dictionary),
};

export async function getDictionary(locale: Locale): Promise<Dictionary> {
  return dictionaries[locale]();
}
