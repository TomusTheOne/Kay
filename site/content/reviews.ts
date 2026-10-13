/* ============================================================================
   Kay's Google reviews, from content/reviews.json.

   Shown as they were written, in the language they were written in. There is
   no Review or AggregateRating markup: Google gives no stars to reviews a
   business shows of itself, and reviews copied from another site are
   against its rules for review snippets. They are here for the reader.
   ========================================================================== */
import data from "./reviews.json";
import { cenoteBySlug } from "./cenotes";
import { PRODUCTS } from "./products";

export interface GoogleReview {
  name: string;
  /** YYYY-MM */
  date: string;
  stars: number;
  /** The language it was written in, as a BCP 47 tag: "en", "es", "fr". */
  lang: string;
  text: string;
  /** Cenote slugs it names, so that cenote's page shows it too. */
  cenotes?: string[];
  /** Product slugs it is about, so that product's page shows it too. */
  products?: string[];
}

export const REVIEWS = data.reviews as GoogleReview[];
export const REVIEWS_URL = data.googleUrl as string | null;
/** The profile's rating and review count on the day they were copied, and that day. */
export const REVIEWS_RATING = data.rating as number | null;
export const REVIEWS_COUNT = data.count as number | null;
export const REVIEWS_AS_OF = data.asOf as string | null;

/* A slug with a typo would quietly drop the review from its cenote page;
   failing the build says which one. */
for (const r of REVIEWS) {
  for (const slug of r.cenotes ?? []) {
    if (!cenoteBySlug(slug)) throw new Error(`reviews.json: ${r.name} names an unknown cenote "${slug}"`);
  }
  for (const slug of r.products ?? []) {
    if (!PRODUCTS.some((p) => p.slug === slug)) throw new Error(`reviews.json: ${r.name} names an unknown product "${slug}"`);
  }
  if (!/^\d{4}-\d{2}$/.test(r.date)) throw new Error(`reviews.json: ${r.name}'s date "${r.date}" is not YYYY-MM`);
}

/**
 * The reviews for one page, the reader's language first and then the newest:
 * a Spanish speaker meets Spanish reviews before English ones, but sees both.
 * With a cenote or a product, only the reviews that name it.
 */
export function reviewsFor(locale: string, only: { cenote?: string; product?: string } = {}) {
  return REVIEWS
    .filter((r) => (!only.cenote || r.cenotes?.includes(only.cenote))
      && (!only.product || r.products?.includes(only.product)))
    .map((r, i) => ({ r, i }))
    .sort((a, b) => Number(b.r.lang === locale) - Number(a.r.lang === locale)
      || b.r.date.localeCompare(a.r.date) || a.i - b.i)
    .map(({ r }) => r);
}

/** "4.9" or "4,9" — the rating with one decimal, the way the locale writes it. */
export function ratingText(locale: string, rating = REVIEWS_RATING) {
  return rating === null ? "" : new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(rating);
}

/** "March 2026", "marzo de 2026", "mars 2026". */
export function monthText(locale: string, ym: string) {
  return new Intl.DateTimeFormat(locale, { month: "long", year: "numeric", timeZone: "UTC" }).format(new Date(`${ym}-01T00:00:00Z`));
}
