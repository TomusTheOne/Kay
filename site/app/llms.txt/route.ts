import { getDictionary, pathFor, LIVE_LOCALES } from "@/lib/i18n";
import { PRODUCTS, SHOP, FROM_PRICE } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED, cenoteBySlug, diveDepth, fromTulum } from "@/content/cenotes";
import { homeDescription } from "@/lib/seo";

/**
 * /llms.txt (llmstxt.org): the site in one page of Markdown for answer
 * engines — who Kay Diving is, what it sells at what price, and every cenote
 * with its depth, the card it needs and where it is. Written from the same
 * content files and English copy as the pages, at build time, so it can
 * never say something the site does not.
 */
export const dynamic = "force-static";

export async function GET() {
  const t = await getDictionary("en");
  const g = t.cenotes;
  const abs = (p: string) => `${SHOP.domain}${p}`;
  const lines: string[] = [];
  const out = (s = "") => lines.push(s);

  out("# Kay Diving — Tulum");
  out();
  out(`> ${homeDescription(t)}`);
  out();
  out(`Kay Diving is a ${SHOP.agency} dive centre in Tulum, Quintana Roo, Mexico. Prices are per person in USD, from $${FROM_PRICE}. `
    + `Water ${SHOP.waterTempC} °C, visibility ${SHOP.visibilityM}. The site is in `
    + LIVE_LOCALES.map((l) => `${{ en: "English", es: "Spanish", fr: "French" }[l]} (${abs(pathFor(l))})`).join(", ") + ".");
  out();
  out("## Book and contact");
  out();
  out(`- [Book online](${abs(pathFor("en"))}#book): a deposit is taken online, the balance is paid on the day.`);
  out(`- Phone: ${SHOP.phoneDisplay}`);
  out(`- Email: ${SHOP.email}`);
  out(`- Instagram: ${SHOP.instagram}`);
  out(`- Meeting point: ${SHOP.meetingPoint}, Tulum`);
  out();
  out("## Dives, courses and tours");
  out();
  for (const p of PRODUCTS) {
    const c = t.products.items[p.slug];
    const prices = p.options.map((o) => (o.dives ? `${o.dives} ${o.dives === 1 ? t.products.dive : t.products.dives}: $${o.price}` : `$${o.price}`)).join(", ");
    const depth = p.maxDepthM ? `, to ${p.maxDepthM} m` : "";
    out(`- **${c.name}** (${prices}${depth}): ${c.text}`);
  }
  out();
  if (GUIDE_PUBLISHED) {
    out(`## Cenotes Kay dives — [guide](${abs(pathFor("en", "/cenotes/"))})`);
    out();
    for (const c of CENOTES) {
      const away = fromTulum(c);
      const facts = [
        g.types[c.type],
        `${g.diveDepth.toLowerCase()} ${diveDepth(c, g.upTo)}`,
        ...(c.depthM !== null && c.depthM > c.diveMaxM ? [`cenote ${c.depthM} m deep`] : []),
        `card: ${g.levels[c.level]}`,
        `${away.km} km ${g.compass[away.dir]} of Tulum`,
        ...(c.snorkel ? ["snorkelling too"] : []),
        ...(c.plan ? ["dive map on the page"] : []),
      ].join(" · ");
      out(`- [${c.name}${c.aka ? ` (${c.aka})` : ""}](${abs(pathFor("en", `/cenotes/${c.slug}/`))}): ${facts}. ${g.metas[c.slug]}`);
    }
    out();
  }
  out("## Questions");
  out();
  for (const q of t.faq.items) {
    out(`### ${q.q}`);
    out();
    out(q.a);
    out();
  }
  out("## Notes");
  out();
  out(`- The deepest dives are ${["angelita", "el-pit"].map((s) => cenoteBySlug(s)?.name).filter(Boolean).join(" and ")}: they need Advanced Open Water or a Deep speciality.`);
  out("- Dive maps on the cenote pages are for orientation, not navigation; the guide briefs the route at the cenote.");

  return new Response(lines.join("\n") + "\n", {
    headers: { "Content-Type": "text/plain; charset=utf-8" },
  });
}
