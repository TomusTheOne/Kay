/* ============================================================================
   What Kay Diving actually sells.
   Every figure here comes from the 2026 menu PDF. Prose lives in the
   dictionaries, keyed by slug; facts live here so a price change updates the
   cards, the booking engine and the structured data at once.

   Depths are the menu's own SPECIAL INFORMATION figures, in feet, converted.
   ========================================================================== */

export type Level = "none" | "open-water" | "open-water-plus";
export type Kind = "dive" | "course" | "snorkel";

/** A product can be bought in more than one size — 1 or 2 dives, 2 or 3. */
export interface Option { dives: number; price: number }

export interface Product {
  slug: string;
  kind: Kind;
  level: Level;
  /** null for the snorkel tour, which is a half day at the surface */
  maxDepthFt: number | null;
  maxDepthM: number | null;
  diveTime: string | null;
  /** dictionary keys, so the place names translate */
  locations: string[];
  options: Option[];
  photo: string | null;
  art: string;
  /** extra include lines beyond the four every product carries */
  extraIncludes?: string[];
}

export const PRODUCTS: Product[] = [
  {
    slug: "discover-scuba", kind: "dive", level: "none",
    maxDepthFt: 30, maxDepthM: 9, diveTime: "45 min",
    locations: ["casa-cenote"],
    options: [{ dives: 1, price: 100 }, { dives: 2, price: 140 }],
    photo: "card-casa", art: "casa-cenote",
  },
  {
    slug: "reef-cenote", kind: "dive", level: "open-water",
    maxDepthFt: 30, maxDepthM: 9, diveTime: "45 min",
    locations: ["casa-cenote", "tankah-reef"],
    options: [{ dives: 2, price: 140 }],
    photo: "reef-turtle", art: "reef",
  },
  {
    slug: "cenote-diving", kind: "dive", level: "open-water-plus",
    maxDepthFt: 60, maxDepthM: 18, diveTime: "30–45 min",
    locations: ["by-level"],
    options: [{ dives: 2, price: 150 }, { dives: 3, price: 170 }],
    photo: "card-angelita", art: "cavern",
  },
  {
    slug: "advanced-ow", kind: "course", level: "open-water",
    maxDepthFt: 90, maxDepthM: 27, diveTime: "30–45 min",
    locations: ["casa-cenote", "el-pit", "tankah-reef"],
    options: [{ dives: 5, price: 460 }],
    photo: "card-dosojos", art: "the-pit",
    extraIncludes: ["materials"],
  },
  {
    slug: "open-water", kind: "course", level: "none",
    maxDepthFt: 30, maxDepthM: 9, diveTime: "30–45 min",
    locations: ["casa-cenote", "tankah-reef"],
    options: [{ dives: 5, price: 450 }],
    photo: "guide", art: "cenote-shaft",
    extraIncludes: ["materials", "padi-licence"],
  },
  {
    slug: "snorkel", kind: "snorkel", level: "none",
    maxDepthFt: null, maxDepthM: null, diveTime: null,
    locations: ["favourite-cenotes"],
    options: [{ dives: 0, price: 80 }],
    photo: "gallery-5", art: "casa-cenote",
    extraIncludes: ["snorkel-mask"],
  },
];

/** Everything on the menu carries these four. */
export const ALWAYS_INCLUDED = ["transport", "equipment", "entrance", "snacks"] as const;

/**
 * Deepest Kay actually dives, confirmed by them: 40 m. The menu's per-product
 * figures below are the depths printed for each course or tour, which are
 * course limits rather than the deepest water they will take a qualified
 * diver into — El Pit alone goes past 40 m.
 */
export const MAX_DEPTH_M = 40;

export const GALLERY = [
  { photo: "gallery-2", tall: true },
  { photo: "gallery-1", tall: false },
  { photo: "gallery-4", tall: false },
  { photo: "gallery-3", tall: false },
  { photo: "gallery-5", tall: false },
];

export const SHOP = {
  lat: 20.2114,
  lon: -87.4654,
  waterTempC: 25,
  visibilityM: "30 m+",
  sacActunKm: 376,
  instagram: "https://www.instagram.com/kaydivingtulum",
  /** Set NEXT_PUBLIC_SITE_URL in the host's environment once the domain is live.
      Everything canonical — sitemap, hreflang, Open Graph — reads from here. */
  domain: (process.env.NEXT_PUBLIC_SITE_URL ?? "https://kaydiving.com").replace(/\/$/, ""),
  agency: "PADI",
} as const;

export const BOOKABLE = PRODUCTS;
