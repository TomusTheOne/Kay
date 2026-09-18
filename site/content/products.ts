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
}

export const PRODUCTS = data.products as Product[];
export const BOOKABLE = PRODUCTS;
export const ALWAYS_INCLUDED = data.alwaysIncluded as string[];
export const GALLERY = data.gallery as { photo: string; tall: boolean }[];

/** Deepest Kay actually dives, confirmed by them. The per-product figures are
    the depths printed for each course or tour, which are course limits. */
export const MAX_DEPTH_M = data.maxDepthM;

export const SHOP = {
  ...data.shop,
  /** Set NEXT_PUBLIC_SITE_URL once the domain is live. */
  domain: (process.env.NEXT_PUBLIC_SITE_URL ?? "https://kaydiving.com").replace(/\/$/, ""),
};
