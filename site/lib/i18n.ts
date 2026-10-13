import { PUBLISHED_LOCALES } from "@/content/products";

/** Every locale the site has copy slots for. */
export const LOCALES = ["en", "es", "fr"] as const;
export type Locale = (typeof LOCALES)[number];
export const DEFAULT_LOCALE: Locale = "en";

/**
 * The locales that are actually built and indexed — the translated ones.
 * Everything that faces a search engine (routes, sitemap, hreflang, the
 * language switcher) reads this, never LOCALES, so an untranslated locale
 * cannot leak out as a duplicate of the English page.
 */
export const LIVE_LOCALES = LOCALES.filter((l) =>
  PUBLISHED_LOCALES.includes(l),
) as readonly Locale[];

export function isLocale(v: string): v is Locale {
  return (LOCALES as readonly string[]).includes(v);
}

/**
 * Open Graph wants a full language_TERRITORY tag, not a bare code — a plain
 * "es" is ignored by the crawlers. Mexico for Spanish, since that is where
 * the shop is and who else reads the page.
 */
export const OG_LOCALE: Record<Locale, string> = {
  en: "en_US",
  es: "es_MX",
  fr: "fr_FR",
};

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
/** One product's own page: its heading, its snippet, its text and its questions. */
export interface ProductPageCopy {
  /** The product as a sentence names it, article included: "the Open Water course", "el curso Open Water". */
  the: string;
  h1: string; title: string; meta: string;
  body: string[];
  faq: Qa[];
}
/** A cenote a visitor can drive to on their own — not a dive site, which is CenoteCopy. */
export interface VisitCopy { name: string; blurb: string; good: string; tip: string }
/** A Maya site within reach of Tulum. */
export interface RuinCopy { name: string; blurb: string; see: string; combine: string }
/** `note` is the second paragraph only the thanks page has: what to bring on the day. */
export interface Outcome { tag: string; h2: string; p: string; note?: string; cta: string }

/**
 * The guide's strings. `items` is Kay's own text for each cenote, keyed by the
 * slugs in content/cenotes.json; `compass` is N, NE, E… NW in that order.
 */
export type CenoteCopy = Record<
  "metaTitle" | "metaDescription" | "tag" | "h1" | "intro" | "preview" | "approx"
  | "mapLabel" | "mapLoading" | "mapError" | "zoomIn" | "zoomOut" | "reset"
  | "gestureWin" | "gestureMac" | "gestureTouch" | "cluster" | "close"
  | "filter" | "all" | "count" | "none" | "diveDepth" | "cenoteDepth" | "upTo"
  | "levelTag" | "fromTulum" | "snorkelToo" | "see" | "divesTag" | "nearbyTag"
  | "back" | "illustration" | "planTag" | "planAlt" | "planNote" | "planCredit" | "planOpen"
  | "pageTitle" | "pageTitleMore" | "breadcrumbHome" | "planCreditCentre" | "aLevelCourse" | "touristType"
  | "faqTag" | "faqH2" | "qDepth" | "aDepth" | "aDepthMore" | "qLevel" | "qWhere" | "aWhere"
  | "qSnorkel" | "aSnorkel" | "qTrips"
  | "artAlt" | "homeTag" | "homeH2" | "homeIntro" | "homeCta", string
> & {
  compass: string[];
  types: Record<"open" | "cavern" | "deep", string>;
  filters: Record<"open" | "cavern" | "deep" | "snorkel", string>;
  levels: Record<"none" | "open-water" | "advanced", string>;
  items: Record<string, string>;
  /** Alt text for each cenote's own photograph, keyed by slug. */
  alts: Record<string, string>;
  /** The answer to "what certification do I need", one per level. */
  aLevel: Record<"none" | "open-water" | "advanced", string>;
  /** Each cenote page's meta description, written for the search result, keyed by slug. */
  metas: Record<string, string>;
  /** Each dive map's alt text, saying what that map shows, keyed by slug. */
  planAlts: Record<string, string>;
  /** "north-east of Tulum" in a sentence: the eight directions with their preposition, N first. */
  compassAt: string[];
};

/*
 * What the client components receive, by name: the map, and the explorer
 * that filters the guide's cards. A list of keys rather than "everything but
 * the server's", so a string added for a server page — a meta description, a
 * map's alt — never ends up in every page's client payload by default.
 */
const MAP_KEYS = [
  "approx", "cenoteDepth", "close", "cluster", "compass", "diveDepth", "fromTulum",
  "gestureMac", "gestureTouch", "gestureWin", "levelTag", "levels", "mapError", "mapLabel",
  "mapLoading", "reset", "see", "snorkelToo", "types", "upTo", "zoomIn", "zoomOut",
] as const;
const CARD_KEYS = [
  ...MAP_KEYS, "artAlt", "illustration", "items", "all", "count", "filter", "filters", "none",
] as const;

export type MapCopy = Pick<CenoteCopy, (typeof MAP_KEYS)[number]>;
export type CardCopy = Pick<CenoteCopy, (typeof CARD_KEYS)[number]>;

function pick<K extends keyof CenoteCopy>(c: Pick<CenoteCopy, K>, keys: readonly K[]) {
  return Object.fromEntries(keys.map((k) => [k, c[k]])) as Pick<CenoteCopy, K>;
}
/** The guide's strings the explorer and its cards need on the client. */
export function cardCopy(c: CardCopy): CardCopy { return pick(c, CARD_KEYS); }
/** The guide's strings the map needs on the client. */
export function mapCopy(c: MapCopy): MapCopy { return pick(c, MAP_KEYS); }

export interface Dictionary {
  meta: { title: string; description: string };
  nav: Record<"about" | "products" | "included" | "logistics" | "gallery" | "faq"
            | "cenotes" | "visit" | "ruins" | "reserve" | "reserveLong" | "menu" | "language", string>;
  hero: Record<"kicker" | "l1" | "l2" | "lede" | "cta2" | "alt"
             | "water" | "viz" | "from" | "agency" | "agencyLabel", string>;
  gauge: Record<"surface" | "cavern" | "deep", string>;
  about: Record<"tag" | "h2" | "p1" | "p2" | "questionTag" | "question"
              | "plateAlt" | "plateNote", string>;
  products: {
    tag: string; h2: string; intro: string;
    from: string; dive: string; dives: string; perDiver: string;
    depth: string; time: string; where: string; needs: string; halfDay: string;
    sitesTag: string;
    /** Months a seasonal product runs, keyed by product slug. */
    seasonValue: Record<string, string>;
    seasonTag: string;
    level: Record<string, string>;
    places: Record<string, string>;
    items: Record<string, ProductCopy>;
    sites: Record<string, { name: string; depth: string; note: string }>;
  };
  logistics: {
    tag: string; h2: string;
    meetTag: string; meetText: string;
    pickupTag: string;
    pickups: Record<string, { name: string; text: string; price: string }>;
    timesTag: string; timesText: string;
    limitsTag: string; limits: string;
  };
  included: {
    tag: string; h2: string; intro: string;
    items: Record<string, IncludeCopy>;
  };
  gallery: { tag: string; h2: string; captions: Record<string, string> };
  /* The two reference sections on the home page. "visit" is cenotes a visitor
     can reach on their own — deliberately not `cenotes`, which the dive guide
     owns. Their label maps are keyed by the values in content/places.json, so
     adding a crowd level or a cenote type there and forgetting the word for it
     here is a compile error rather than a blank cell. */
  visit: {
    tag: string; h2: string; intro: string;
    typeLabel: Record<string, string>;
    activityLabel: Record<string, string>;
    weDive: string; weDiveCta: string;
    goodLabel: string; tipLabel: string;
    note: string;
    items: Record<string, VisitCopy>;
  };
  ruins: {
    tag: string; h2: string; intro: string;
    fromLabel: string; stayLabel: string; entryLabel: string; hoursLabel: string;
    lastEntryLabel: string; seeLabel: string; combineLabel: string;
    crowdLabel: Record<string, string>;
    walkLabel: Record<string, string>;
    transitLabel: Record<string, string>;
    nationalLabel: string; foreignLabel: string; culturLabel: string;
    parkingLabel: string; extraPossible: string; bookingRequired: string;
    priceNote: string; ferryNote: string;
    items: Record<string, RuinCopy>;
  };
  quote: { text: string; cite: string };
  /** The product pages (app/[locale]/[page]), and the links to them. */
  product: {
    kinds: Record<"dive" | "course" | "snorkel", string>;
    bookKind: Record<"dive" | "course" | "snorkel", string>;
    ask: string; whatsappText: string; more: string; back: string;
    priceTag: string; includedTag: string; whereTag: string; snorkelWhere: string;
    faqTag: string; faqH2: string; otherTag: string;
    qPrice: string; aPrice: string; qMeet: string; aMeet: string; aMeetSea: string;
    qBook: string; aBook: string;
    items: Record<string, ProductPageCopy>;
  };
  /** Kay's Google reviews, wherever content/reviews.json has some. */
  reviews: Record<"tag" | "h2" | "h2Plain" | "intro" | "introPlain" | "all" | "stars"
                | "badge" | "cenoteH2", string>;
  book: Record<string, string> & { certs: string[] };
  faq: { tag: string; h2: string; items: Qa[] };
  cta: Record<"kicker" | "h2" | "lede" | "instagram" | "bandAlt", string>;
  footer: Record<"blurb" | "explore" | "learn" | "find" | "whatsapp" | "email"
                | "place" | "phone" | "meet", string>;
  booking: Record<"thanks" | "pending" | "failed", Outcome>;
  /** The equipment-sizes questionnaire behind the link in the confirmation email. */
  gear: Record<string, string>;
  /** The cenote guide, its map, and the map section on the home page. */
  cenotes: CenoteCopy;
  /* Sent by php/lib/notify.php once a deposit clears. The strings live with
     the rest of the copy so a translation stays one file, not two. */
  emails: {
    common: Record<"dive" | "dives" | "halfDay" | "divers" | "diver"
                 | "slotOther" | "noCert", string>;
    diver: Record<string, string>;
    shop: Record<string, string>;
  };
}

/**
 * French sets a narrow no-break space before : ; ! ? » and after «. The
 * messages are typed with plain spaces, which a browser may break at,
 * leaving a lone "?" at the start of a line; this swaps them for U+202F
 * once, at load. Only the site reads it — the PHP emails read fr.json as is.
 */
function frenchSpacing<T>(v: T): T {
  if (typeof v === "string") {
    return v.replace(/ ([:;!?»])/g, "\u202F$1").replace(/« /g, "«\u202F") as T;
  }
  if (Array.isArray(v)) return v.map(frenchSpacing) as T;
  if (v && typeof v === "object") {
    return Object.fromEntries(Object.entries(v).map(([k, x]) => [k, frenchSpacing(x)])) as T;
  }
  return v;
}

const dictionaries: Record<Locale, () => Promise<Dictionary>> = {
  en: () => import("../messages/en.json").then((m) => m.default as unknown as Dictionary),
  es: () => import("../messages/es.json").then((m) => m.default as unknown as Dictionary),
  fr: () => import("../messages/fr.json").then((m) => frenchSpacing(m.default as unknown as Dictionary)),
};

export async function getDictionary(locale: Locale): Promise<Dictionary> {
  return dictionaries[locale]();
}
