import { notFound } from "next/navigation";
import { isLocale, getDictionary, pathFor } from "@/lib/i18n";
import { expMetadata, experiencesFor } from "@/lib/exp";
import { PRODUCTS, ALWAYS_INCLUDED, SHOP, CONTENT_UPDATED } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED } from "@/content/cenotes";
import GuideFrame from "@/components/GuideFrame";
import Booking from "@/components/Booking";

/**
 * One page per thing Kay sells.
 *
 * The home page lists all seven on one screen, which answers "what do you
 * do" and nothing else. Somebody searching "curso open water tulum" is
 * asking a narrower question, and the page that answers exactly it is the
 * one Google shows. This is that page: what it is, who it is for, how the
 * day goes, what it costs, where it happens, and the form, without a scroll
 * through six other experiences first.
 *
 * Only the experiences whose copy exists in THIS locale are built — see
 * lib/exp.ts. An empty page in a third language would cost more than it
 * earns.
 */

type Params = { params: Promise<{ locale: string; product: string }> };

export const dynamicParams = false;

export async function generateStaticParams({ params }: { params: { locale: string } }) {
  const { locale } = params;
  if (!isLocale(locale)) return [];
  return (await experiencesFor(locale)).map((product) => ({ product }));
}

export async function generateMetadata({ params }: Params) {
  const { locale, product } = await params;
  if (!isLocale(locale)) return {};
  const t = await getDictionary(locale);
  const copy = t.exp.items[product];
  if (!copy) return {};
  return expMetadata(locale, product, copy.title, copy.description);
}

export default async function ExperiencePage({ params }: Params) {
  const { locale, product } = await params;
  if (!isLocale(locale)) notFound();
  const t = await getDictionary(locale);
  const copy = t.exp.items[product];
  const p = PRODUCTS.find((x) => x.slug === product);
  if (!copy || !p) notFound();

  const home = pathFor(locale);
  const path = `/${product}/`;
  const url = `${SHOP.domain}${pathFor(locale, path)}`;
  const cheapest = Math.min(...p.options.map((o) => o.price));
  const dearest = Math.max(...p.options.map((o) => o.price));
  const included = [...ALWAYS_INCLUDED, ...(p.extraIncludes ?? [])];
  const guideSlugs = new Set(CENOTES.map((c) => c.slug));

  /* Google warns on an Offer with no expiry; a year from the last catalogue
     change is honest, as on the home page. */
  const priceValidUntil = new Date(
    new Date(CONTENT_UPDATED).setFullYear(new Date(CONTENT_UPDATED).getFullYear() + 1),
  ).toISOString().slice(0, 10);

  /* Product and Offer are Kay's own — unlike the reviews, these are first
     party and belong in structured data. The breadcrumb tells Google where
     the page sits; the questions are this page's, not the home page's. */
  const ld = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Product",
        "@id": `${url}#product`,
        name: copy.h1,
        description: copy.description,
        brand: { "@type": "Organization", name: "Kay Diving" },
        offers: p.options.map((o) => ({
          "@type": "Offer",
          price: o.price, priceCurrency: "USD", availability: "https://schema.org/InStock",
          url, priceValidUntil,
        })),
      },
      {
        "@type": "BreadcrumbList",
        "@id": `${url}#crumbs`,
        itemListElement: [
          { "@type": "ListItem", position: 1, name: "Kay Diving", item: `${SHOP.domain}${home}` },
          { "@type": "ListItem", position: 2, name: t.exp.crumb, item: `${SHOP.domain}${home}#products` },
          { "@type": "ListItem", position: 3, name: copy.h1 },
        ],
      },
      {
        "@type": "FAQPage",
        "@id": `${url}#faq`,
        mainEntity: copy.faq.map((qa) => ({
          "@type": "Question", name: qa.q,
          acceptedAnswer: { "@type": "Answer", text: qa.a },
        })),
      },
    ],
  };

  return (
    <GuideFrame t={t} locale={locale} path={path} preview={false}>
      <script type="application/ld+json"
              dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} />

      <article className="exp shell">
        <nav className="crumbs data" aria-label={t.exp.crumb}>
          <a href={home}>Kay Diving</a> <span aria-hidden="true">·</span>{" "}
          <a href={`${home}#products`}>{t.exp.crumb}</a>
        </nav>

        <header className="exp__head">
          <p className="tag">{t.products.items[p.slug].name}</p>
          <h1 className="dsp dsp-lg">{copy.h1}</h1>
          <p className="lede">{copy.lede}</p>
          {/* Only the rows this experience actually has. The snorkel tour
              and the shark trip carry no depth and no bottom time in the
              catalogue, and an empty "max depth: m" is worse than no row —
              it reads as a number nobody bothered to fill in. */}
          <dl className="exp__facts data">
            <div><dt>{t.products.from}</dt>
              <dd><b>${cheapest}</b>{dearest !== cheapest ? ` – $${dearest}` : ""} USD</dd></div>
            {p.maxDepthM ? (
              <div><dt>{t.products.depth}</dt>
                <dd><b>{p.maxDepthM} m</b> · {p.maxDepthFt} ft</dd></div>
            ) : null}
            {p.diveTime ? (
              <div><dt>{t.products.time}</dt><dd><b>{p.diveTime}</b></dd></div>
            ) : p.kind === "snorkel" ? (
              <div><dt>{t.products.time}</dt><dd><b>{t.products.halfDay}</b></dd></div>
            ) : null}
            {p.season ? (
              <div><dt>{t.products.seasonTag}</dt>
                <dd><b>{t.products.seasonValue[p.slug]}</b></dd></div>
            ) : null}
            <div><dt>{t.products.needs}</dt><dd><b>{t.products.level[p.level]}</b></dd></div>
          </dl>
        </header>

        <section className="exp__bay">
          <p className="tag">{t.exp.whoTag}</p>
          <p className="lede">{copy.who}</p>
        </section>

        <section className="exp__bay">
          <p className="tag">{t.exp.dayTag}</p>
          {copy.day.map((para, i) => <p className="lede" key={i}>{para}</p>)}
        </section>

        <section className="exp__bay">
          <p className="tag">{t.exp.includedTag}</p>
          <ul className="exp__inc">
            {included.map((slug) => (
              <li key={slug}>
                <b>{t.included.items[slug].name}</b>
                <span className="muted">{t.included.items[slug].text}</span>
              </li>
            ))}
          </ul>
        </section>

        <section className="exp__bay">
          <p className="tag">{t.exp.sitesTag}</p>
          <p className="lede">{copy.sites}</p>
          <ul className="exp__sites">
            {(p.sites ?? []).map((s) => {
              const name = t.products.sites[s.slug]?.name ?? s.slug;
              const note = t.products.sites[s.slug]?.note ?? "";
              const depth = t.products.sites[s.slug]?.depth ?? "";
              const linked = GUIDE_PUBLISHED && guideSlugs.has(s.slug);
              const body = (
                <>
                  <b>{name}</b> <span className="data">{depth}</span>
                  <span className="muted">{note}</span>
                </>
              );
              return (
                <li key={s.slug}>
                  {linked
                    ? <a href={pathFor(locale, `/cenotes/${s.slug}/`)}>{body}</a>
                    : body}
                </li>
              );
            })}
            {!p.sites && (p.locations ?? []).map((loc) => (
              <li key={loc}><b>{t.products.places[loc]}</b></li>
            ))}
          </ul>
        </section>

        <section className="exp__bay">
          <p className="tag">{t.exp.faqTag}</p>
          <div className="faq">
            {copy.faq.map((qa) => (
              <details key={qa.q}>
                <summary>{qa.q}<i aria-hidden="true" /></summary>
                <p>{qa.a}</p>
              </details>
            ))}
          </div>
        </section>

        <section className="exp__bay" id="book">
          <p className="tag">{t.exp.bookTag}</p>
          <h2 className="dsp dsp-md">{t.exp.bookH2}</h2>
          <div style={{ marginTop: "1.6rem" }}>
            <Booking t={t.book} products={t.products} logistics={t.logistics}
                     locale={locale} initial={p.slug} />
          </div>
        </section>

        <p className="exp__more"><a className="lnk" href={`${home}#products`}>{t.exp.more}</a></p>
      </article>
    </GuideFrame>
  );
}
