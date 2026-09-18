import { notFound } from "next/navigation";
import { isLocale, getDictionary, pathFor, LOCALES } from "@/lib/i18n";
import { PRODUCTS, ALWAYS_INCLUDED, GALLERY, PICKUPS, SCHEDULES, SHOP } from "@/content/products";
import Surface from "@/components/Surface";
import Gauge from "@/components/Gauge";
import Reveals from "@/components/Reveals";
import Booking from "@/components/Booking";

function Plate({ photo, art, alt, eager = false }: {
  photo: string | null; art: string; alt: string; eager?: boolean;
}) {
  if (!photo) {
    return <img src={`/assets/art/${art}.svg`} alt={alt}
                loading={eager ? undefined : "lazy"} width={1200} height={800} />;
  }
  return (
    <picture>
      <source srcSet={`/assets/photos/${photo}.avif`} type="image/avif" />
      <source srcSet={`/assets/photos/${photo}.webp`} type="image/webp" />
      <img src={`/assets/photos/${photo}.webp`} alt={alt}
           fetchPriority={eager ? "high" : undefined}
           loading={eager ? undefined : "lazy"} decoding={eager ? undefined : "async"}
           width={1200} height={800} />
    </picture>
  );
}

const Arrow = () => (
  <svg width="14" height="8" viewBox="0 0 14 8" fill="none" aria-hidden="true">
    <path d="M1 4h11M9 1l3 3-3 3" stroke="currentColor" strokeWidth="1.4"
          strokeLinecap="round" strokeLinejoin="round" />
  </svg>
);

export default async function Home({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();
  const t = await getDictionary(locale);

  const nav = [
    { href: "#about", label: t.nav.about },
    { href: "#products", label: t.nav.products },
    { href: "#included", label: t.nav.included },
    { href: "#logistics", label: t.nav.logistics },
    { href: "#gallery", label: t.nav.gallery },
    { href: "#faq", label: t.nav.faq },
  ];

  const cheapest = Math.min(...PRODUCTS.flatMap((p) => p.options.map((o) => o.price)));

  /** Every include line on the page, in menu order, without duplicates. */
  const includeKeys = [
    ...ALWAYS_INCLUDED,
    ...[...new Set(PRODUCTS.flatMap((p) => p.extraIncludes ?? []))],
  ];

  const ld = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "SportsActivityLocation",
        "@id": `${SHOP.domain}/#business`,
        name: "Kay Diving",
        description: t.meta.description,
        url: `${SHOP.domain}${pathFor(locale)}`,
        telephone: SHOP.phone,
        priceRange: `$${cheapest}–$${Math.max(...PRODUCTS.flatMap((p) => p.options.map((o) => o.price)))}`,
        image: `${SHOP.domain}/assets/photos/hero.webp`,
        address: { "@type": "PostalAddress", addressLocality: "Tulum",
                   addressRegion: "Quintana Roo", addressCountry: "MX" },
        geo: { "@type": "GeoCoordinates", latitude: SHOP.lat, longitude: SHOP.lon },
        sameAs: [SHOP.instagram],
      },
      {
        "@type": "ItemList",
        itemListElement: PRODUCTS.map((p, i) => ({
          "@type": "ListItem", position: i + 1,
          item: {
            "@type": "Product",
            name: t.products.items[p.slug].name,
            description: t.products.items[p.slug].text,
            offers: p.options.map((o) => ({
              "@type": "Offer", price: String(o.price), priceCurrency: "USD",
              availability: "https://schema.org/InStock",
              ...(o.dives ? { name: `${o.dives} ${o.dives === 1 ? t.products.dive : t.products.dives}` } : {}),
            })),
          },
        })),
      },
      {
        "@type": "FAQPage",
        mainEntity: t.faq.items.map((q) => ({
          "@type": "Question", name: q.q,
          acceptedAnswer: { "@type": "Answer", text: q.a },
        })),
      },
    ],
  };

  return (
    <>
      <script type="application/ld+json"
              dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} />
      <Reveals />
      <Gauge zones={[[10, t.gauge.surface], [26, t.gauge.cavern], [99, t.gauge.deep]]} />
      <Surface locale={locale} nav={nav} reserve={t.nav.reserve} reserveLong={t.nav.reserveLong}
               menuLabel={t.nav.menu} langLabel={t.nav.language} />

      <main id="main">
        {/* ------------------------------------------------------------ hero */}
        <section className="hero">
          <div className="hero__art"><Plate photo="hero" art="cenote-shaft" alt={t.hero.alt} eager /></div>
          <div className="hero__scrim" />
          <div className="caustics" aria-hidden="true" />
          <div className="bubbles" aria-hidden="true">
            {[["12%", 5, 17, 0, 22], ["26%", 3, 23, 3, -16], ["44%", 7, 20, 7, 30],
              ["61%", 4, 26, 1.5, -24], ["73%", 6, 19, 9, 18], ["88%", 3, 24, 5, -12]]
              .map(([left, size, d, dl, x], i) => (
                <i key={i} style={{ left, width: `${size}px`, height: `${size}px`,
                     "--d": `${d}s`, "--dl": `${dl}s`, "--x": `${x}px` } as React.CSSProperties} />
              ))}
          </div>

          <div className="hero__in shell">
            <p className="tag">{t.hero.kicker}</p>
            <h1 className="dsp dsp-xl">
              <span className="l1">{t.hero.l1}</span>
              <span className="l2 ital grad">{t.hero.l2}</span>
            </h1>
            <p className="lede hero__lede">{t.hero.lede}</p>
            <div className="hero__cta">
              <a className="btn btn--lit" href="#book">{t.nav.reserveLong} <Arrow /></a>
              <a className="btn btn--ghost" href="#products">{t.hero.cta2}</a>
            </div>
            <p className="strip data">
              <span>{SHOP.lat.toFixed(2)}°N {Math.abs(SHOP.lon).toFixed(2)}°W</span><i className="dot" />
              <span>{t.hero.water} <b>{SHOP.waterTempC}°C</b></span><i className="dot" />
              <span>{t.hero.viz} <b>{SHOP.visibilityM}</b></span><i className="dot" />
              <span>{t.hero.agencyLabel} <b>{SHOP.agency}</b></span><i className="dot" />
              <span>{t.hero.from} <b>${cheapest}</b></span>
            </p>
          </div>
        </section>

        {/* ----------------------------------------------------------- about */}
        <section className="bay shell" id="about">
          <div className="split">
            <div className="plate" data-rise>
              <Plate photo="guide" art="cavern" alt={t.about.plateAlt} />
              <div className="plate__note data">{t.about.plateNote}</div>
            </div>
            <div data-rise data-rise-d="1">
              <p className="tag">{t.about.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}>{t.about.h2}</h2>
              <p className="lede" style={{ marginTop: "1.4rem" }}>{t.about.p1}</p>
              <p className="lede" style={{ marginTop: "1rem" }}>{t.about.p2}</p>
              <div style={{ marginTop: "2rem", paddingLeft: "1.2rem", borderLeft: "2px solid var(--turq)" }}>
                <p className="tag tag--plain">{t.about.questionTag}</p>
                <p className="lede" style={{ marginTop: ".7rem" }}>{t.about.question}</p>
              </div>
            </div>
          </div>
        </section>

        {/* -------------------------------------------------------- products */}
        <section className="bay shell" id="products">
          <div className="head2">
            <div data-rise>
              <p className="tag">{t.products.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem", maxWidth: "16ch" }}
                  dangerouslySetInnerHTML={{ __html: t.products.h2 }} />
            </div>
            <p className="muted" data-rise data-rise-d="1" style={{ maxWidth: "40ch" }}>{t.products.intro}</p>
          </div>

          <div className="dives">
            {PRODUCTS.map((p, i) => {
              const copy = t.products.items[p.slug];
              return (
                <article className="dive" key={p.slug} data-rise data-rise-d={i % 4 || undefined}>
                  <div className="dive__media">
                    <Plate photo={p.photo} art={p.art} alt={copy.alt} />
                    <span className="dive__depth">
                      {p.maxDepthM ? `MAX ${p.maxDepthM} M · ${p.maxDepthFt} FT` : t.products.halfDay.toUpperCase()}
                    </span>
                  </div>
                  <div className="dive__body">
                    <h3 className="dive__name">{copy.name}</h3>
                    <p className="data" style={{ color: "var(--turq)", marginTop: "-.25rem" }}>{copy.tagline}</p>
                    <p className="dive__txt">{copy.text}</p>

                    {p.sites && (
                      <div className="sites">
                        <p className="sites__t">{t.products.sitesTag}</p>
                        {p.sites.filter((x) => x.deep).map((x) => (
                          <div className="sites__r" key={x.slug}>
                            <span>{t.products.sites[x.slug].name}</span>
                            <b>{t.products.sites[x.slug].depth}</b>
                          </div>
                        ))}
                      </div>
                    )}

                    <dl className="spec">
                      <div><dt>{t.products.needs}</dt><dd>{t.products.level[p.level]}</dd></div>
                      <div><dt>{t.products.where}</dt>
                        <dd>{p.locations.map((l) => t.products.places[l]).join(" · ")}</dd></div>
                      {p.diveTime && <div><dt>{t.products.time}</dt><dd>{p.diveTime}</dd></div>}
                    </dl>

                    <ul className="prices">
                      {p.options.map((o) => (
                        <li key={o.dives}>
                          <span>{o.dives
                            ? `${o.dives} ${o.dives === 1 ? t.products.dive : t.products.dives}`
                            : t.products.halfDay}</span>
                          <b>${o.price}</b>
                        </li>
                      ))}
                      <li className="prices__note"><span>{t.products.perDiver}</span></li>
                    </ul>
                  </div>
                </article>
              );
            })}
          </div>
        </section>

        {/* -------------------------------------------------------- included */}
        <section className="bay shell" id="included">
          <div className="head2">
            <div data-rise>
              <p className="tag">{t.included.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}
                  dangerouslySetInnerHTML={{ __html: t.included.h2 }} />
            </div>
            <p className="muted" data-rise data-rise-d="1" style={{ maxWidth: "38ch" }}>{t.included.intro}</p>
          </div>
          <div className="courses" data-rise>
            {includeKeys.map((k) => (
              <article className="course" key={k}>
                <h3 className="course__n">{t.included.items[k].name}</h3>
                <p className="course__d">{t.included.items[k].text}</p>
              </article>
            ))}
          </div>
        </section>

        {/* ------------------------------------------------------- logistics */}
        <section className="bay shell" id="logistics">
          <div className="head2">
            <div data-rise>
              <p className="tag">{t.logistics.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}
                  dangerouslySetInnerHTML={{ __html: t.logistics.h2 }} />
            </div>
            <div data-rise data-rise-d="1" style={{ maxWidth: "38ch" }}>
              <p className="tag tag--plain">{t.logistics.meetTag}</p>
              <p className="muted" style={{ marginTop: ".6rem" }}>{t.logistics.meetText}</p>
            </div>
          </div>

          <div className="split" style={{ alignItems: "start" }}>
            <div data-rise>
              <p className="tag tag--plain">{t.logistics.pickupTag}</p>
              <ul className="pick" style={{ marginTop: "1.1rem" }}>
                {PICKUPS.map((p) => (
                  <li key={p.slug}>
                    <div>
                      <b>{t.logistics.pickups[p.slug].name}</b>
                      <span>{t.logistics.pickups[p.slug].text}</span>
                    </div>
                    <em>{t.logistics.pickups[p.slug].price}</em>
                  </li>
                ))}
              </ul>
            </div>

            <div data-rise data-rise-d="1">
              <p className="tag tag--plain">{t.logistics.timesTag}</p>
              <div className="slots" style={{ marginTop: "1.1rem" }}>
                {SCHEDULES.filter((s) => s.start).map((s) => (
                  <span className="slot" key={s.slug}>{s.start}–{s.end}</span>
                ))}
              </div>
              <p className="muted" style={{ marginTop: "1.1rem" }}>{t.logistics.timesText}</p>
              <div style={{ marginTop: "1.8rem", paddingLeft: "1.2rem", borderLeft: "2px solid var(--turq)" }}>
                <p className="tag tag--plain">{t.logistics.limitsTag}</p>
                <p className="muted" style={{ marginTop: ".6rem" }}>{t.logistics.limits}</p>
              </div>
            </div>
          </div>
        </section>

        {/* --------------------------------------------------------- gallery */}
        <section className="bay shell" id="gallery">
          <div className="head2">
            <div data-rise>
              <p className="tag">{t.gallery.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}
                  dangerouslySetInnerHTML={{ __html: t.gallery.h2 }} />
            </div>
            <p className="muted" data-rise data-rise-d="1" style={{ maxWidth: "38ch" }}>
              {t.gallery.intro}{" "}
              <a href={SHOP.instagram} rel="noopener" className="tq">@kaydivingtulum</a>.
            </p>
          </div>
          <div className="gal" data-rise>
            {GALLERY.map((g) => (
              <figure className="gal__t" key={g.photo}>
                <Plate photo={g.photo} art="cenote-shaft" alt={t.gallery.captions[g.photo]} />
                <figcaption>{t.gallery.captions[g.photo]}</figcaption>
              </figure>
            ))}
          </div>
        </section>

        {/* ----------------------------------------------------------- quote */}
        <section className="bay shell">
          <blockquote className="quote" data-rise>
            “{t.quote.text}”<cite>{t.quote.cite}</cite>
          </blockquote>
        </section>

        {/* ------------------------------------------------------------ book */}
        <section className="bay shell" id="book">
          <div className="head2">
            <div data-rise>
              <p className="tag">{t.book.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}
                  dangerouslySetInnerHTML={{ __html: t.book.h2 }} />
            </div>
            <p className="muted" data-rise data-rise-d="1" style={{ maxWidth: "38ch" }}>{t.book.intro}</p>
          </div>
          <div data-rise>
            <Booking t={t.book} products={t.products} logistics={t.logistics} locale={locale} />
          </div>
        </section>

        {/* ------------------------------------------------------------- faq */}
        <section className="bay shell" id="faq">
          <div className="head2">
            <div data-rise>
              <p className="tag">{t.faq.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}
                  dangerouslySetInnerHTML={{ __html: t.faq.h2 }} />
            </div>
          </div>
          <div className="faq" data-rise>
            {t.faq.items.map((q, i) => (
              <details key={q.q} open={i === 0}>
                <summary>{q.q}</summary>
                <p>{q.a}</p>
              </details>
            ))}
          </div>
        </section>

        {/* ------------------------------------------------------------- cta */}
        <section className="bay wide">
          <div className="band" data-rise>
            <div className="band__art"><Plate photo="band" art="the-pit" alt="" /></div>
            <p className="tag tag--plain" style={{ justifyContent: "center" }}>{t.cta.kicker}</p>
            <h2 className="dsp dsp-lg" style={{ marginTop: "1rem" }}>{t.cta.h2}</h2>
            <p className="lede" style={{ margin: "1.2rem auto 0" }}>{t.cta.lede}</p>
            <div className="hero__cta" style={{ justifyContent: "center", marginTop: "2rem" }}>
              <a className="btn btn--lit" href="#book">{t.nav.reserveLong} <Arrow /></a>
              <a className="btn btn--ghost" href={SHOP.instagram} rel="noopener">{t.cta.instagram}</a>
            </div>
          </div>
        </section>
      </main>

      <footer className="foot">
        <div className="shell">
          <div className="foot__grid">
            <div>
              <p className="muted" style={{ fontSize: ".9rem", maxWidth: "36ch" }}>{t.footer.blurb}</p>
            </div>
            <div><h4>{t.footer.explore}</h4><ul>
              {PRODUCTS.filter((p) => p.kind !== "course").map((p) => (
                <li key={p.slug}><a href="#products">{t.products.items[p.slug].name}</a></li>
              ))}
            </ul></div>
            <div><h4>{t.footer.learn}</h4><ul>
              {PRODUCTS.filter((p) => p.kind === "course").map((p) => (
                <li key={p.slug}><a href="#products">{t.products.items[p.slug].name}</a></li>
              ))}
              <li><a href="#included">{t.nav.included}</a></li>
              <li><a href="#faq">{t.nav.faq}</a></li>
            </ul></div>
            <div><h4>{t.footer.find}</h4><ul>
              <li><a href={SHOP.instagram} rel="noopener">@kaydivingtulum</a></li>
              <li><a href={`tel:${SHOP.phone}`}>{SHOP.phoneDisplay}</a></li>
              <li><a href={`https://wa.me/${SHOP.phone.replace(/\D/g, "")}`} rel="noopener">{t.footer.whatsapp}</a></li>
              <li><span className="muted" style={{ fontSize: ".9rem" }}>{t.footer.meet}: {SHOP.meetingPoint}</span></li>
              <li><span className="muted" style={{ fontSize: ".9rem" }}>{t.footer.place}</span></li>
            </ul></div>
          </div>
          <div className="foot__base data">
            <span>© {new Date().getFullYear()} Kay Diving · Tulum</span>
            <span>{LOCALES.map((l) => l.toUpperCase()).join(" · ")}</span>
          </div>
        </div>
      </footer>
    </>
  );
}
