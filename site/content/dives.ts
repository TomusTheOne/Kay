/* ============================================================================
   Single source of truth for what Kay sells.
   Facts live here (depth, price, level, photograph); prose lives in the
   dictionaries, keyed by slug. One change to a price updates the cards, the
   booking engine and the structured data at once.
   ========================================================================== */

export type Level = "open-water" | "advanced" | "deep";

export interface Dive {
  slug: string;
  maxDepth: number;      // metres, planned not possible
  level: Level;
  price: number;         // USD per diver
  tanks: number;
  /** file stem in /assets/photos, or null while we still use an illustration */
  photo: string | null;
  /** fallback illustration in /assets/art */
  art: string;
}

export const DIVES: Dive[] = [
  { slug: "first-light", maxDepth: 8,  level: "open-water", price: 180, tanks: 2, photo: "card-casa",     art: "casa-cenote" },
  { slug: "decorated",   maxDepth: 10, level: "open-water", price: 210, tanks: 2, photo: "card-dosojos",  art: "cavern" },
  { slug: "angelita",    maxDepth: 30, level: "advanced",   price: 240, tanks: 2, photo: "card-angelita", art: "halocline" },
  { slug: "the-pit",     maxDepth: 40, level: "deep",       price: 250, tanks: 2, photo: null,            art: "the-pit" },
  { slug: "reef",        maxDepth: 18, level: "open-water", price: 140, tanks: 2, photo: "reef-turtle",   art: "reef" },
];

export const BOOKABLE = DIVES;

export interface Addon { slug: string; price: number; perDiver: boolean }
export const ADDONS: Addon[] = [
  { slug: "photos",  price: 70, perDiver: false },
  { slug: "private", price: 95, perDiver: false },
  { slug: "nitrox",  price: 35, perDiver: true  },
  { slug: "third",   price: 80, perDiver: true  },
];

export interface Pickup { slug: string; price: number }
export const PICKUPS: Pickup[] = [
  { slug: "shop",   price: 0  },
  { slug: "tulum",  price: 25 },
  { slug: "akumal", price: 45 },
];

export const COURSES = [
  { slug: "open-water", price: 520, days: "4"   },
  { slug: "advanced",   price: 430, days: "2"   },
  { slug: "cavern",     price: 650, days: "3"   },
  { slug: "nitrox",     price: 250, days: "1"   },
];

export const GALLERY = [
  { photo: "gallery-2", tall: true  },
  { photo: "gallery-1", tall: false },
  { photo: "gallery-4", tall: false },
  { photo: "gallery-3", tall: false },
  { photo: "gallery-5", tall: false },
];

/** Facts shown in the hero instrument strip and the JSON-LD. */
export const SHOP = {
  lat: 20.2114,
  lon: -87.4654,
  waterTempC: 25,
  visibilityM: "30 m+",
  sacActunKm: 376,
  maxDiversPerGuide: 4,
  cenotesInRotation: 12,
  instagram: "https://www.instagram.com/kaydivingtulum",
  domain: "https://kaydiving.com",
} as const;
