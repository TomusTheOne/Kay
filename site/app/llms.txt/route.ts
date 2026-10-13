import { getDictionary, pathFor, LIVE_LOCALES } from "@/lib/i18n";
import { PRODUCTS, SHOP, FROM_PRICE, FROM_DIVE_PRICE } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED, diveDepth, fromTulum } from "@/content/cenotes";
import { homeDescription } from "@/lib/seo";
import { REVIEWS_RATING, REVIEWS_COUNT, REVIEWS_AS_OF, REVIEWS_URL } from "@/content/reviews";

/**
 * /llms.txt (llmstxt.org): the site in one page of Markdown for answer
 * engines — who Kay Diving is, what it sells at what price, and every cenote
 * with its depth, the card it needs and where it is. Written from the same
 * content files and English copy as the pages, at build time, so it can
 * never say something the site does not.
 *
 * The shape is the spec's: an H1, a blockquote, free Markdown with no
 * headings (the facts and the FAQ), then H2 sections that hold nothing but
 * "- [name](url): note" lines, which is all a parser reads from them.
 */
export const dynamic = "force-static";

export async function GET() {
  const t = await getDictionary("en");
  const g = t.cenotes;
  const abs = (p: string) => `${SHOP.domain}${p}`;
  const lines: string[] = [];
  const out = (s = "") => lines.push(s);
  const language = { en: "English", es: "Spanish", fr: "French" } as const;

  out("# Kay Diving — Tulum");
  out();
  out(`> ${homeDescription(t)}`);
  out();
  out(`Kay Diving is a ${SHOP.agency} dive centre in Tulum, Quintana Roo, Mexico. Prices are per person in USD: `
    + `dives from $${FROM_DIVE_PRICE}, the snorkel tour $${FROM_PRICE}. Water ${SHOP.waterTempC} °C, visibility ${SHOP.visibilityM}. `
    + `A deposit is taken online when booking; the balance is paid on the day.`);
  out();
  out(`Contact: phone ${SHOP.phoneDisplay} · ${SHOP.email} · Instagram ${SHOP.instagram}. `
    + `Meeting point: ${SHOP.meetingPoint}, Tulum.`);
  out();
  /* The profile's own figures, dated, since they move. */
  if (REVIEWS_RATING !== null && REVIEWS_COUNT !== null && REVIEWS_AS_OF) {
    out(`Rated ${REVIEWS_RATING.toFixed(1)} out of 5 on Google from ${REVIEWS_COUNT} reviews (as of ${REVIEWS_AS_OF})`
      + `${REVIEWS_URL ? `: ${REVIEWS_URL}` : ""}.`);
    out();
  }
  out("Dives, courses and tours:");
  out();
  for (const p of PRODUCTS) {
    const c = t.products.items[p.slug];
    const prices = p.options.map((o) => (o.dives
      ? `${o.dives} ${o.dives === 1 ? t.products.dive : t.products.dives} $${o.price}` : `$${o.price}`)).join(", ");
    const depth = p.maxDepthM ? `, to ${p.maxDepthM} m` : "";
    out(`- **${c.name}** (${prices}${depth}): ${c.text}`);
  }
  out();
  out("Frequently asked:");
  out();
  for (const q of t.faq.items) {
    out(`**${q.q}** ${q.a}`);
    out();
  }
  out("The dive maps on the cenote pages are for orientation, not navigation: the guide briefs the route at the cenote.");
  out();

  if (GUIDE_PUBLISHED) {
    out("## Cenotes");
    out();
    for (const c of CENOTES) {
      const away = fromTulum(c);
      const facts = [
        g.types[c.type],
        `dive ${diveDepth(c, g.upTo)}`,
        ...(c.depthM !== null && c.depthM > c.diveMaxM ? [`cenote ${c.depthM} m deep`] : []),
        `card: ${g.levels[c.level]}`,
        `about ${away.km} km ${g.compassAt[away.dir]} of Tulum${c.approx ? " (position approximate)" : ""}`,
        ...(c.snorkel ? ["snorkelling too"] : []),
        ...(c.plan ? ["dive map on the page"] : []),
      ].join(" · ");
      out(`- [${c.name}${c.aka ? ` (${c.aka})` : ""}](${abs(pathFor("en", `/cenotes/${c.slug}/`))}): ${facts}. ${g.metas[c.slug]}`);
    }
    out();
  }

  out("## Pages");
  out();
  for (const l of LIVE_LOCALES) {
    out(`- [Home, ${language[l]}](${abs(pathFor(l))}): dives, courses, prices, booking and the FAQ`);
    if (GUIDE_PUBLISHED) out(`- [Cenote guide, ${language[l]}](${abs(pathFor(l, "/cenotes/"))}): every cenote on a map, filtered by type`);
  }
  out(`- [Book a dive](${abs(pathFor("en"))}#book): choose the dive, the date and the divers; a deposit is paid online`);
  out(`- [Instagram](${SHOP.instagram}): photos and news from the dives`);

  return new Response(lines.join("\n") + "\n", {
    headers: { "Content-Type": "text/plain; charset=utf-8" },
  });
}
