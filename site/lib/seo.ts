import { pathFor, type Dictionary, type Locale } from "@/lib/i18n";
import { SHOP, FROM_PRICE } from "@/content/products";
import { diveDepth, fromTulum, type Cenote } from "@/content/cenotes";

/**
 * What the search result and the answer engines read. Everything a sentence
 * here states — a price, a depth, a distance — is computed from the content
 * files, so the snippet cannot drift from the page the way "from $80" did.
 */

/** Google cuts a title at about 60 characters; the brand goes last. */
const TITLE_MAX = 60;
const BRAND = " | Kay Diving";

export function homeDescription(t: Dictionary) {
  return t.meta.description.replace("{from}", String(FROM_PRICE));
}

/** "Cenote Angelita Diving in Tulum: Depth & Map | Kay Diving", the tail dropped when it would be cut. */
export function cenoteTitle(t: Dictionary, c: Cenote) {
  const g = t.cenotes;
  const head = g.pageTitle.replace("{name}", c.name);
  const full = head + g.pageTitleMore + BRAND;
  return full.length <= TITLE_MAX ? full : head + BRAND;
}

/** The dive's depth as a figure: "30–32 m", "16 m" — never "to 7 m", which reads badly in a sentence. */
export function diveFigure(c: Cenote) {
  return c.diveMinM ? `${c.diveMinM}–${c.diveMaxM} m` : `${c.diveMaxM} m`;
}

/**
 * The questions a diver types about one cenote, answered from its data. Shown
 * on the page and given as FAQPage, so the visible text and the markup are
 * the same words.
 */
export function cenoteQa(t: Dictionary, c: Cenote) {
  const g = t.cenotes;
  const n = (s: string) => s.replaceAll("{name}", c.name);
  const away = fromTulum(c);
  const deeper = c.depthM !== null && c.depthM > c.diveMaxM;
  const trips = c.products.map((p) => t.products.items[p].name);
  return [
    { q: n(g.qDepth), a: g.aDepth.replace("{dive}", diveFigure(c))
        + (deeper ? ` ${g.aDepthMore.replace("{depth}", String(c.depthM))}` : "") },
    { q: n(g.qLevel), a: g.aLevel[c.level] },
    { q: n(g.qWhere), a: g.aWhere.replace("{km}", String(away.km)).replace("{dir}", g.compass[away.dir]) },
    ...(c.snorkel ? [{ q: n(g.qSnorkel), a: n(g.aSnorkel) }] : []),
    { q: n(g.qTrips), a: `${trips.join(", ")}.` },
  ];
}

/** Facts as schema.org PropertyValue, the form answer engines lift a figure from. */
export function cenoteFacts(t: Dictionary, c: Cenote) {
  const g = t.cenotes;
  const away = fromTulum(c);
  const pv = (name: string, value: string | number, unitCode?: string) =>
    ({ "@type": "PropertyValue", name, value, ...(unitCode ? { unitCode } : {}) });
  return [
    pv(g.diveDepth, diveDepth(c, g.upTo)),
    ...(c.depthM !== null ? [pv(g.cenoteDepth, c.depthM, "MTR")] : []),
    pv(g.levelTag, g.levels[c.level]),
    pv(g.fromTulum, away.km, "KMT"),
  ];
}

/** BreadcrumbList for a guide page: home, the guide, and the cenote if there is one. */
export function guideBreadcrumb(t: Dictionary, locale: Locale, c?: Cenote) {
  const items = [
    { name: t.cenotes.breadcrumbHome, path: "/" },
    { name: t.cenotes.tag, path: "/cenotes/" },
    ...(c ? [{ name: c.name, path: `/cenotes/${c.slug}/` }] : []),
  ];
  return {
    "@type": "BreadcrumbList",
    itemListElement: items.map((it, i) => ({
      "@type": "ListItem", position: i + 1, name: it.name,
      item: `${SHOP.domain}${pathFor(locale, it.path)}`,
    })),
  };
}

/** geo.* and ICBM: read by Bing and a few regional engines, ignored by the rest at no cost. */
export function geoMeta(lat: number, lon: number, place = "Tulum") {
  const pos = `${lat.toFixed(5)};${lon.toFixed(5)}`;
  return { "geo.region": "MX-ROO", "geo.placename": place, "geo.position": pos, ICBM: pos.replace(";", ", ") };
}
