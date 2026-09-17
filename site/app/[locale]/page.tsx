import { notFound } from "next/navigation";
import { isLocale, getDictionary, pathFor, LOCALES } from "@/lib/i18n";
import { DIVES, COURSES, GALLERY, SHOP } from "@/content/dives";
import Surface from "@/components/Surface";
import Gauge from "@/components/Gauge";
import Reveals from "@/components/Reveals";
import Booking from "@/components/Booking";

/** A photograph when we have one, the hand-drawn scene when we do not. */
function Plate({ photo, art, alt, eager = false, className = "" }: {
  photo: string | null; art: string; alt: string; eager?: boolean; className?: string;
}) {
  if (!photo) {
    return <img className={className} src={`/assets/art/${art}.svg`} alt={alt}
                loading={eager ? undefined : "lazy"} width={1200} height={800} />;
  }
  return (
    <picture>
      <source srcSet={`/assets/photos/${photo}.avif`} type="image/avif" />
      <source srcSet={`/assets/photos/${photo}.webp`} type="image/webp" />
      <img className={className} src={`/assets/photos/${photo}.webp`} alt={alt}
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
    { href: "#underworld", label: t.nav.underworld },
    { href: "#dives", label: t.nav.dives },
    { href: "#reef", label: t.nav.reef },
    { href: "#courses", label: t.nav.courses },
    { href: "#gallery", label: t.nav.gallery },
    { href: "#faq", label: t.nav.faq },
  ];
  const cards = DIVES.filter((d) => d.slug !== "reef");

  const ld = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "SportsActivityLocation",
        "@id": `${SHOP.domain}/#business`,
        name: "Kay Diving",
        description: t.meta.description,
        url: `${SHOP.domain}${pathFor(locale)}`,
        priceRange: "$$",
        image: `${SHOP.domain}/assets/photos/hero.webp`,
        address: { "@type": "PostalAddress", addressLocality: "Tulum",
                   addressRegion: "Quintana Roo", addressCountry: "MX" },
        geo: { "@type": "GeoCoordinates", latitude: SHOP.lat, longitude: SHOP.lon },
        sameAs: [SHOP.instagram],
      },
      {
        "@type": "ItemList",
        itemListElement: DIVES.map((d, i) => ({
          "@type": "ListItem", position: i + 1,
          item: {
            "@type": "Product", name: t.dives.items[d.slug].name, description: t.dives.items[d.slug].text,
            offers: { "@type": "Offer", price: String(d.price), priceCurrency: "USD",
                      availability: "https://schema.org/InStock" },
          },
        })),
      },
      {
        "@type": "FAQPage",
        mainEntity: t.faq.items.map((q: { q: string; a: string }) => ({
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
      <Gauge zones={[[6, t.gauge.surface], [18, t.gauge.cavern], [30, t.gauge.deep], [99, t.gauge.abyss]]} />
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
              <a className="btn btn--ghost" href="#dives">{t.hero.cta2}</a>
            </div>
            <p className="strip data">
              <span>{SHOP.lat.toFixed(2)}°N {Math.abs(SHOP.lon).toFixed(2)}°W</span><i className="dot" />
              <span>{t.hero.water} <b>{SHOP.waterTempC}°C</b></span><i className="dot" />
              <span>{t.hero.viz} <b>{SHOP.visibilityM}</b></span><i className="dot" />
              <span>{t.hero.sacActun} <b>{SHOP.sacActunKm} KM</b></span><i className="dot" />
              <span>{t.hero.max} <b>{SHOP.maxDiversPerGuide} {t.hero.divers}</b></span>
            </p>
          </div>
        </section>

        {/* ------------------------------------------------------ underworld */}
        <section className="bay shell" id="underworld">
          <div className="split">
            <div className="plate" data-rise>
              <Plate photo="guide" art="cavern" alt={t.underworld.plateAlt} />
              <div className="plate__note data">{t.underworld.plateNote}</div>
            </div>
            <div data-rise data-rise-d="1">
              <p className="tag">{t.underworld.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}>{t.underworld.h2}</h2>
              <p className="lede" style={{ marginTop: "1.4rem" }}>{t.underworld.p1}</p>
              <p className="lede" style={{ marginTop: "1rem" }}>{t.underworld.p2}</p>
              <div className="tally">
                {[[String(SHOP.maxDiversPerGuide), t.underworld.stat1],
                  [`${SHOP.waterTempC}°`, t.underworld.stat2],
                  [String(SHOP.cenotesInRotation), t.underworld.stat3],
                  [`${SHOP.sacActunKm}`, t.underworld.stat4]].map(([n, l]) => (
                  <div key={l}>
                    <div className="tally__n">{n}</div>
                    <div className="tally__l" dangerouslySetInnerHTML={{ __html: l }} />
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* ----------------------------------------------------------- dives */}
        <section className="bay shell" id="dives">
          <div className="head2">
            <div data-rise>
              <p className="tag">{t.dives.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem", maxWidth: "16ch" }}
                  dangerouslySetInnerHTML={{ __html: t.dives.h2 }} />
            </div>
            <p className="muted" data-rise data-rise-d="1" style={{ maxWidth: "38ch" }}>{t.dives.intro}</p>
          </div>
          <div className="dives">
            {cards.map((d, i) => (
              <article className="dive" key={d.slug} data-rise data-rise-d={i || undefined}>
                <div className="dive__media">
                  <Plate photo={d.photo} art={d.art} alt={t.dives.items[d.slug].alt} />
                  <span className="dive__depth">MAX {d.maxDepth} M · {t.dives.level[d.level]}</span>
                </div>
                <div className="dive__body">
                  <h3 className="dive__name">{t.dives.items[d.slug].name}</h3>
                  <p className="dive__txt">{t.dives.items[d.slug].text}</p>
                  <div className="dive__foot">
                    <span className="dive__price data">{t.dives.from} <b>${d.price}</b></span>
                    <span className="dive__go">{d.tanks} {t.dives.tanksLabel} <Arrow /></span>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>

        {/* ------------------------------------------------------------ reef */}
        <section className="bay shell" id="reef">
          <div className="feat feat--rev">
            <div className="feat__media" data-rise>
              <Plate photo="reef-turtle" art="reef" alt={t.dives.items.reef.alt} />
            </div>
            <div data-rise data-rise-d="1">
              <p className="tag">{t.reef.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}>{t.reef.h2}</h2>
              <p className="lede" style={{ marginTop: "1.3rem" }}>{t.reef.lede}</p>
              <ul className="ticks">
                {[t.reef.t1, t.reef.t2, t.reef.t3, t.reef.t4].map((x: string) => (
                  <li key={x}>
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                      <path d="M3 8.5l3.2 3.2L13 5" stroke="currentColor" strokeWidth="1.7"
                            strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                    <span>{x}</span>
                  </li>
                ))}
              </ul>
              <a className="btn btn--ghost" href="#book" style={{ marginTop: "2rem" }}>{t.reef.cta}</a>
            </div>
          </div>
        </section>

        {/* --------------------------------------------------------- courses */}
        <section className="bay shell" id="courses">
          <div className="head2">
            <div data-rise>
              <p className="tag">{t.courses.tag}</p>
              <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }}
                  dangerouslySetInnerHTML={{ __html: t.courses.h2 }} />
            </div>
            <p className="muted" data-rise data-rise-d="1" style={{ maxWidth: "38ch" }}>{t.courses.intro}</p>
          </div>
          <div className="courses" data-rise>
            {COURSES.map((c) => (
              <article className="course" key={c.slug}>
                <p className="course__lvl">{t.courses.items[c.slug].level}</p>
                <h3 className="course__n">{t.courses.items[c.slug].name}</h3>
                <p className="course__d">{t.courses.items[c.slug].text}</p>
                <p className="course__p">${c.price} · {c.days} {c.days === "1" ? t.courses.day : t.courses.days}</p>
              </article>
            ))}
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
            <Booking t={{ ...t.book, level: t.dives.level, ...Object.fromEntries(DIVES.map((d) => [d.slug, t.dives.items[d.slug]])) }}
                     locale={locale} />
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
            {t.faq.items.map((q: { q: string; a: string }, i: number) => (
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

      {/* ---------------------------------------------------------- footer */}
      <footer className="foot">
        <div className="shell">
          <div className="foot__grid">
            <div>
              <p className="muted" style={{ fontSize: ".9rem", maxWidth: "34ch" }}>{t.footer.blurb}</p>
            </div>
            <div><h4>{t.footer.dive}</h4><ul>
              <li><a href="#dives">{t.nav.dives}</a></li><li><a href="#reef">{t.nav.reef}</a></li>
              <li><a href="#courses">{t.nav.courses}</a></li><li><a href="#book">{t.nav.reserve}</a></li>
            </ul></div>
            <div><h4>{t.footer.cenotes}</h4><ul>
              {cards.map((d) => <li key={d.slug}><a href="#dives">{t.dives.items[d.slug].name}</a></li>)}
            </ul></div>
            <div><h4>{t.footer.find}</h4><ul>
              <li><a href={SHOP.instagram} rel="noopener">@kaydivingtulum</a></li>
              <li><a href="#book">{t.footer.whatsapp}</a></li><li><a href="#book">{t.footer.email}</a></li>
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
