import data from "./reviews.json";

/**
 * What divers wrote about Kay on Google, transcribed by hand.
 *
 * Deliberately not fetched at build time and deliberately not marked up as
 * structured data. Google's policy is that reviews collected on a third-party
 * site — theirs included — are not eligible for review rich results on yours,
 * and marking them up as first-party Review/AggregateRating is a manual-action
 * risk. They are here to be read by people, which is what they are for.
 *
 * Kept in the diver's own language, whatever the page's language: a review
 * translated by the shop it praises is not a review any more. The chrome
 * around it — the heading, the year, the link — is translated as usual.
 */
export interface Review {
  name: string;
  stars: number;
  /** Google gives a relative age, so a year is all anyone can honestly claim. */
  year: string;
  lang: string;
  text: string;
}

export const REVIEWS = data.items as Review[];

/** What the listing says in total, which five quotes cannot say: a quote can
    be invented, an average of thirty cannot. Text only — see above. */
export const SCORE = { rating: data.rating as string, count: data.count as number };
