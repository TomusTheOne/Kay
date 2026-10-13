/* ============================================================================
   The address of each product's own page, in each language.

   One page per thing Kay sells, so that "PADI Open Water course Tulum" or
   "bautismo de buceo Tulum" finds a page about exactly that, rather than the
   home page that lists all seven. The slug is in the reader's language and
   carries the words people search with; the page itself is
   app/[locale]/[page]/page.tsx. Changing a slug moves a page that search
   engines have indexed — it needs a 301 in public-htaccess.
   ========================================================================== */
import { PRODUCTS, type Product } from "./products";

type Locales = "en" | "es" | "fr";

export const PRODUCT_PATHS: Record<string, Record<Locales, string>> = {
  "discover-scuba": {
    en: "discover-scuba-diving-tulum", es: "bautismo-de-buceo-tulum", fr: "bapteme-de-plongee-tulum",
  },
  "reef-cenote": {
    en: "reef-and-cenote-diving-tulum", es: "buceo-arrecife-y-cenote-tulum", fr: "plongee-recif-et-cenote-tulum",
  },
  "cenote-diving": {
    en: "cenote-diving-tulum", es: "buceo-en-cenotes-tulum", fr: "plongee-cenotes-tulum",
  },
  "open-water": {
    en: "padi-open-water-course-tulum", es: "curso-padi-open-water-tulum", fr: "formation-padi-open-water-tulum",
  },
  "advanced-ow": {
    en: "padi-advanced-open-water-tulum", es: "curso-padi-advanced-open-water-tulum",
    fr: "formation-padi-advanced-open-water-tulum",
  },
  snorkel: {
    en: "cenote-snorkeling-tour-tulum", es: "tour-de-snorkel-en-cenotes-tulum", fr: "snorkeling-cenotes-tulum",
  },
  "bull-sharks": {
    en: "bull-shark-diving-playa-del-carmen", es: "buceo-con-tiburon-toro-playa-del-carmen",
    fr: "plongee-requins-bouledogues-playa-del-carmen",
  },
};

/* A product without an address would be a page that silently does not
   exist; failing the build names it. */
for (const p of PRODUCTS) {
  if (!PRODUCT_PATHS[p.slug]) throw new Error(`content/pages.ts: no page address for product "${p.slug}"`);
}

/** "/padi-open-water-course-tulum/" — below the locale, the form pathFor() takes. */
export function productPath(locale: string, slug: string) {
  return `/${PRODUCT_PATHS[slug][locale as Locales]}/`;
}

/** Every language's path for one product, for the language switcher and hreflang. */
export function productPaths(slug: string) {
  return Object.fromEntries(
    Object.entries(PRODUCT_PATHS[slug]).map(([l, s]) => [l, `/${s}/`]),
  ) as Record<Locales, string>;
}

/**
 * Where a product page sends its reader next: the natural next step first
 * (Discover Scuba → the Open Water course → Advanced), then neighbours.
 */
export const NEXT_PRODUCTS: Record<string, string[]> = {
  "discover-scuba": ["open-water", "snorkel", "reef-cenote"],
  "reef-cenote": ["cenote-diving", "open-water", "discover-scuba"],
  "cenote-diving": ["advanced-ow", "reef-cenote", "bull-sharks"],
  "open-water": ["advanced-ow", "cenote-diving", "reef-cenote"],
  "advanced-ow": ["cenote-diving", "bull-sharks", "open-water"],
  snorkel: ["discover-scuba", "cenote-diving", "reef-cenote"],
  "bull-sharks": ["advanced-ow", "cenote-diving", "reef-cenote"],
};

/** The product a page address in one language belongs to. */
export function productBySegment(locale: string, segment: string): Product | undefined {
  return PRODUCTS.find((p) => PRODUCT_PATHS[p.slug][locale as Locales] === segment);
}
