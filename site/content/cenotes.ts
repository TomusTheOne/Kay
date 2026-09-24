/* ============================================================================
   Types and helpers over content/cenotes.json — the cenote guide and its map.

   Positions, depths and levels are the same in every language, so they live
   in the JSON; the words live in messages/<locale>.json under cenotes.items.
   ========================================================================== */
import data from "./cenotes.json";
import { SHOP } from "./products";

/** Open to the sky, a cavern under a roof of rock, or past the 20 m line. */
export type CenoteType = "open" | "cavern" | "deep";
/** The card a diver shows on the day: none, Open Water, or Advanced/Deep. */
export type CenoteLevel = "none" | "open-water" | "advanced";

export interface Cenote {
  slug: string;
  /** "Cenote Angelita" — headings and page titles */
  name: string;
  /** "Angelita" — map labels, where space is short */
  short: string;
  /** The Mayan name, when the cenote is better known by another one */
  aka?: string;
  lat: number;
  lon: number;
  /** Not surveyed from a public source: Kay places it before going live. */
  approx: boolean;
  type: CenoteType;
  level: CenoteLevel;
  /** Kay's text says it is good for snorkelling too. */
  snorkel: boolean;
  /** Deepest point of the cenote itself, where Kay's text gives it */
  depthM: number | null;
  /** How deep Kay's dives there go */
  diveMinM: number | null;
  diveMaxM: number;
  /** Products in content/products.json that dive it */
  products: string[];
  photo: string | null;
  art: string;
}

export interface Town { name: string; lat: number; lon: number; home?: boolean }

export const CENOTES = data.cenotes as Cenote[];
export const TOWNS = data.towns as Town[];
export const BASEMAP = data.basemap;
/** [west, south, east, north] — what the basemap covers, and so how far the map pans. */
export const MAP_BOUNDS = data.bounds as [number, number, number, number];

/**
 * False while Kay checks the positions on the preview pages. Until then the
 * guide is reachable only by its address: noindex, out of the sitemap, out of
 * the menu, and the home page shows no map.
 */
export const GUIDE_PUBLISHED = data.published as boolean;

export function cenoteBySlug(slug: string) {
  return CENOTES.find((c) => c.slug === slug);
}

/** Great-circle distance in km. */
export function km(a: { lat: number; lon: number }, b: { lat: number; lon: number }) {
  const rad = Math.PI / 180;
  const dLat = (b.lat - a.lat) * rad;
  const dLon = (b.lon - a.lon) * rad;
  const h = Math.sin(dLat / 2) ** 2
    + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLon / 2) ** 2;
  return 6371 * 2 * Math.asin(Math.sqrt(h));
}

/** Eight-point compass bearing from a to b, as an index into N, NE, E… NW. */
export function bearing8(a: { lat: number; lon: number }, b: { lat: number; lon: number }) {
  const rad = Math.PI / 180;
  const y = Math.sin((b.lon - a.lon) * rad) * Math.cos(b.lat * rad);
  const x = Math.cos(a.lat * rad) * Math.sin(b.lat * rad)
    - Math.sin(a.lat * rad) * Math.cos(b.lat * rad) * Math.cos((b.lon - a.lon) * rad);
  const deg = (Math.atan2(y, x) / rad + 360) % 360;
  return Math.round(deg / 45) % 8;
}

/** Straight-line distance and direction from Tulum, which is where every dive starts. */
export function fromTulum(c: Cenote) {
  const tulum = { lat: SHOP.lat, lon: SHOP.lon };
  return { km: Math.round(km(tulum, c)), dir: bearing8(tulum, c) };
}

/** The n cenotes closest to this one. */
export function nearest(c: Cenote, n = 3) {
  return CENOTES.filter((o) => o.slug !== c.slug)
    .map((o) => ({ o, d: km(c, o) }))
    .sort((a, b) => a.d - b.d)
    .slice(0, n)
    .map(({ o }) => o);
}

/**
 * How deep the dive goes, not the cenote: "30–32 m", "16 m". Where Kay has not
 * given a depth, his rule for every other cenote applies — no deeper than
 * 18 m — and `upTo` says so in the reader's language ("to {m} m").
 */
export function diveDepth(c: Cenote, upTo: string) {
  if (c.diveMinM) return `${c.diveMinM}–${c.diveMaxM} m`;
  return c.depthM === null ? upTo.replace("{m}", String(c.diveMaxM)) : `${c.diveMaxM} m`;
}
