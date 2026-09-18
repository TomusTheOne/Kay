/* ============================================================================
   Types and derived values over content/products.json.

   The JSON is the single source of truth: this file reads it, and so do the
   PHP endpoints in php/. A price therefore cannot disagree between the page
   that shows it and the server that charges it.
   ========================================================================== */
import data from "./products.json";

export type Level = "none" | "open-water" | "open-water-plus";
export type Kind = "dive" | "course" | "snorkel";

export interface Option { dives: number; price: number }

/** A cenote you can pick inside Cenote Diving. */
export interface Site {
  slug: string;
  minM: number | null;
  maxM: number | null;
  /** past 30 m, so it needs Advanced or a Deep speciality */
  deep: boolean;
}

export interface Product {
  slug: string;
  kind: Kind;
  level: Level;
  maxDepthFt: number | null;
  maxDepthM: number | null;
  diveTime: string | null;
  locations: string[];
  options: Option[];
  photo: string | null;
  art: string;
  extraIncludes: string[];
  sites?: Site[];
}

export interface Pickup {
  slug: string;
  /** USD, per booking — it is one van, not one seat */
  price: number;
  /** outside town Kay can only do the drive back */
  returnOnly: boolean;
}

export interface Schedule {
  slug: string;
  start: string | null;
  end: string | null;
}

export const PRODUCTS = data.products as Product[];
export const BOOKABLE = PRODUCTS;
export const ALWAYS_INCLUDED = data.alwaysIncluded as string[];
export const GALLERY = data.gallery as { photo: string; tall: boolean }[];
export const PICKUPS = data.pickups as Pickup[];
export const SCHEDULES = data.schedules as Schedule[];
export const SCHEDULE_LIMITS = data.scheduleLimits as {
  twoDiveLatestStart: string;
  casaCenoteSingleLatestStart: string;
};

/** Deepest site Kay names: Cenote Angelita at 35–38 m. */
export const MAX_DEPTH_M = data.maxDepthM;

/**
 * The day the offer last changed, for <lastmod> in the sitemap. Deliberately
 * not the build date: a redeploy that changes nothing must not tell Google the
 * page is new, or the signal stops meaning anything. Bump it when prices,
 * depths or copy change.
 */
export const CONTENT_UPDATED = data.contentUpdated;

/**
 * Which locales actually go live. A locale whose messages are still the
 * English originals must NOT ship: three URLs carrying the same English text,
 * each claiming a different hreflang, is duplicate content — it costs ranking
 * rather than earning it. Add "es" and "fr" here the day messages/es.json and
 * messages/fr.json are really translated; build-deploy.mjs refuses to build if
 * this list runs ahead of them.
 */
export const PUBLISHED_LOCALES = data.publishedLocales as string[];

export const SHOP = {
  ...data.shop,
  /**
   * Set NEXT_PUBLIC_SITE_URL once the domain is live. `||`, not `??`: an
   * unset GitHub Actions variable expands to an EMPTY STRING, not undefined,
   * and `??` would have happily accepted it — shipping a sitemap, canonicals,
   * hreflang and og:image all pointing at "". Empty falls back too.
   */
  domain: (process.env.NEXT_PUBLIC_SITE_URL || "https://kaydiving.com").replace(/\/$/, ""),
};
