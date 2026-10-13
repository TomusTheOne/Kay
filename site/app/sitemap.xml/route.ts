import { LIVE_LOCALES, DEFAULT_LOCALE, pathFor, type Locale } from "@/lib/i18n";
import { SHOP, CONTENT_UPDATED, PRODUCTS, ogCard } from "@/content/products";
import { productPaths } from "@/content/pages";
import { CENOTES, GUIDE_PUBLISHED, GUIDE_UPDATED, cenoteBySlug, mapFile } from "@/content/cenotes";

/**
 * /sitemap.xml, written out rather than through MetadataRoute.Sitemap: Next
 * emits the hreflang links and images before <lastmod>, which the
 * sitemaps.org schema does not allow. Here every <url> is loc, lastmod,
 * changefreq, priority, then the language siblings, then the images.
 *
 * One entry per page and locale, each declaring its siblings so Google pairs
 * them. The cenote guide joins only once it is published: until then its
 * pages are noindex, and listing a noindex page here would contradict it.
 * A product page's address is translated, so its siblings are each
 * language's own path.
 */
export const dynamic = "force-static";

const abs = (p: string) => `${SHOP.domain}${p}`;
const esc = (s: string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

/** The images the page shows, so a photograph or a dive map is found with the page it belongs to. */
function images(path: string, locale: Locale): string[] {
  if (path === "/") return [abs("/assets/photos/hero.webp"), abs(ogCard(locale))];
  if (path === "/cenotes/") return CENOTES.filter((c) => c.photo).map((c) => abs(`/assets/photos/${c.photo}.webp`));
  const c = cenoteBySlug(path.match(/^\/cenotes\/([^/]+)\/$/)?.[1] ?? "");
  if (!c) return [];
  return [
    ...(c.photo ? [abs(`/assets/photos/${c.photo}.webp`)] : []),
    ...(c.plan ? [abs(mapFile(c, locale, c.plan.w * 2, "webp"))] : []),
  ];
}

type Page = { at: (l: Locale) => string; lastmod: string; priority: (l: Locale) => string;
               images: (l: Locale) => string[] };

export function GET() {
  const same = (path: string) => () => path;
  const pages: Page[] = [
    { at: same("/"), lastmod: CONTENT_UPDATED, priority: (l) => (l === "en" ? "1.0" : "0.8"),
      images: (l) => images("/", l) },
    /* The pages that sell: one per product, just under the home page. */
    ...PRODUCTS.map((p): Page => ({
      at: (l) => productPaths(p.slug)[l], lastmod: CONTENT_UPDATED, priority: () => "0.8",
      images: () => (p.photo ? [abs(`/assets/photos/${p.photo}.webp`)] : []),
    })),
    ...(GUIDE_PUBLISHED ? ["/cenotes/", ...CENOTES.map((c) => `/cenotes/${c.slug}/`)] : []).map((path): Page => ({
      at: same(path), lastmod: GUIDE_UPDATED, priority: () => "0.6", images: (l) => images(path, l),
    })),
  ];
  const urls = pages.flatMap((page) => LIVE_LOCALES.map((locale) => {
    const siblings = [
      ...LIVE_LOCALES.map((l) => [l, abs(pathFor(l, page.at(l)))]),
      ["x-default", abs(pathFor(DEFAULT_LOCALE, page.at(DEFAULT_LOCALE)))],
    ];
    return [
      "<url>",
      `<loc>${esc(abs(pathFor(locale, page.at(locale))))}</loc>`,
      `<lastmod>${page.lastmod}</lastmod>`,
      "<changefreq>monthly</changefreq>",
      `<priority>${page.priority(locale)}</priority>`,
      ...siblings.map(([l, href]) => `<xhtml:link rel="alternate" hreflang="${l}" href="${esc(href)}" />`),
      ...page.images(locale).map((src) => `<image:image><image:loc>${esc(src)}</image:loc></image:image>`),
      "</url>",
    ].join("\n");
  }));
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
${urls.join("\n")}
</urlset>
`;
  return new Response(xml, { headers: { "Content-Type": "application/xml; charset=utf-8" } });
}
