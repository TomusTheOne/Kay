import { pathFor, type Dictionary, type Locale } from "@/lib/i18n";
import { SHOP, FROM_DIVE_PRICE, PRODUCTS } from "@/content/products";
import { diveDepth, fromTulum, type Cenote } from "@/content/cenotes";

/**
 * What the search result and the answer engines read. Everything a sentence
 * here states — a price, a depth, a distance — is computed from the content
 * files, so the snippet cannot drift from the page the way "from $80" did.
 * The home snippet speaks of dives, so its price is the cheapest dive, not
 * the snorkel tour.
 */

/** Google cuts a title at about 60 characters; the brand goes last. */
const TITLE_MAX = 60;
const BRAND = " | Kay Diving";

export function homeDescription(t: Dictionary) {
  return t.meta.description.replace("{from}", String(FROM_DIVE_PRICE));
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
  /* A no-break space keeps the figure and its unit on one line. */
  return c.diveMinM ? `${c.diveMinM}–${c.diveMaxM}\u00a0m` : `${c.diveMaxM}\u00a0m`;
}

/** The cenote's own depth is a fact worth stating only where it is deeper than the dive. */
const deeperThanDive = (c: Cenote) => c.depthM !== null && c.depthM > c.diveMaxM;

/**
 * A cenote's name as a sentence needs it after a preposition. Spanish and
 * French take an article before "Cenote X" — "au Cenote Angelita", "en el
 * Cenote El Pit" — and none before a name like Casa Cenote. Templates use
 * {name}, {the}, {at}, {to} and {of}.
 */
export function nameForms(locale: Locale, name: string) {
  const cenote = name.startsWith("Cenote ");
  if (locale === "fr") {
    return cenote
      ? { the: `le ${name}`, at: `au ${name}`, to: `au ${name}`, of: `du ${name}` }
      : { the: name, at: `à ${name}`, to: `à ${name}`, of: `${/^[aeiouyhé]/i.test(name) ? "d'" : "de "}${name}` };
  }
  if (locale === "es") {
    return cenote
      ? { the: `el ${name}`, at: `en el ${name}`, to: `al ${name}`, of: `del ${name}` }
      : { the: name, at: `en ${name}`, to: `a ${name}`, of: `de ${name}` };
  }
  return { the: name, at: `at ${name}`, to: `to ${name}`, of: `of ${name}` };
}

export function fillName(s: string, locale: Locale, name: string) {
  const f = nameForms(locale, name);
  return s.replaceAll("{name}", name).replaceAll("{the}", f.the).replaceAll("{at}", f.at)
    .replaceAll("{to}", f.to).replaceAll("{of}", f.of);
}

/** "Redrawn from the map by …", with French eliding "de" before a vowel: "d'après le plan d'Alejandro…". */
export function planCredit(t: Dictionary, locale: Locale, credit: string | null) {
  const g = t.cenotes;
  if (!credit) return g.planCreditCentre;
  if (locale === "fr" && /^[aeiouyhé]/i.test(credit)) return g.planCredit.replace("de {credit}", `d'${credit}`);
  return g.planCredit.replace("{credit}", credit);
}

/** What one dive map shows, for its alt text and its social card. */
export function planAlt(t: Dictionary, locale: Locale, c: Cenote) {
  return t.cenotes.planAlts[c.slug] ?? fillName(t.cenotes.planAlt, locale, c.name);
}

/**
 * The questions a diver types about one cenote, answered from its data. Shown
 * on the page and given as FAQPage, so the visible text and the markup are
 * the same words.
 */
export function cenoteQa(t: Dictionary, locale: Locale, c: Cenote) {
  const g = t.cenotes;
  const n = (s: string) => fillName(s, locale, c.name);
  const away = fromTulum(c);
  const trips = c.products.map((p) => t.products.items[p].name);
  /* An Advanced card is the rule for the deep dives, but the Advanced course
     itself takes Open Water divers there — to the course's own depth. */
  const course = PRODUCTS.find((p) => p.slug === "advanced-ow");
  const viaCourse = c.level === "advanced" && course?.maxDepthM && c.products.includes(course.slug)
    ? ` ${g.aLevelCourse.replace("{depth}", String(course.maxDepthM))}` : "";
  return [
    { q: n(g.qDepth), a: g.aDepth.replace("{dive}", diveFigure(c))
        + (deeperThanDive(c) ? ` ${g.aDepthMore.replace("{depth}", String(c.depthM))}` : "") },
    { q: n(g.qLevel), a: g.aLevel[c.level] + viaCourse },
    { q: n(g.qWhere), a: g.aWhere.replace("{km}", String(away.km)).replace("{dir}", g.compassAt[away.dir]) },
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
    ...(deeperThanDive(c) ? [pv(g.cenoteDepth, c.depthM as number, "MTR")] : []),
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

/**
 * A page that must not be indexed or shared: the booking outcomes and the
 * gear form. The locale layout gives every page the home page's canonical,
 * hreflang set and Open Graph URL; a noindex page carrying them points
 * crawlers at the home page from a page they are told to drop.
 */
export const PRIVATE_PAGE = {
  robots: { index: false, follow: false },
  alternates: null,
  openGraph: null,
  twitter: null,
} as const;

/** The nodes every guide graph points at, defined again so each page's graph resolves on its own. */
export function siteNodes(locale: Locale) {
  return [
    { "@type": "WebSite", "@id": `${SHOP.domain}/#website`, url: SHOP.domain, name: "Kay Diving Tulum" },
    { "@type": "SportsActivityLocation", "@id": `${SHOP.domain}/#business`, name: "Kay Diving",
      url: `${SHOP.domain}${pathFor(locale)}` },
  ];
}

/** geo.* and ICBM: read by Bing and a few regional engines, ignored by the rest at no cost. */
export function geoMeta(lat: number, lon: number, place = "Tulum") {
  const pos = `${lat.toFixed(5)};${lon.toFixed(5)}`;
  return { "geo.region": "MX-ROO", "geo.placename": place, "geo.position": pos, ICBM: pos.replace(";", ", ") };
}
