import { LIVE_LOCALES, pathFor, type Dictionary, type Locale } from "@/lib/i18n";
import { PRODUCTS, SHOP } from "@/content/products";
import { GUIDE_PUBLISHED } from "@/content/cenotes";

/**
 * The site footer. `home` prefixes the in-page links: empty on the home page,
 * "/en/" on the guide pages, so "Dives & courses" still lands on the section.
 */
export default function Footer({ t, locale, home = "" }: { t: Dictionary; locale: Locale; home?: string }) {
  return (
    <footer className="foot">
      <div className="shell">
        <div className="foot__grid">
          <div>
            <p className="muted" style={{ fontSize: ".9rem", maxWidth: "36ch" }}>{t.footer.blurb}</p>
          </div>
          <div><h4>{t.footer.explore}</h4><ul>
            {PRODUCTS.filter((p) => p.kind !== "course").map((p) => (
              <li key={p.slug}><a href={`${home}#products`}>{t.products.items[p.slug].name}</a></li>
            ))}
            {GUIDE_PUBLISHED && <li><a href={pathFor(locale, "/cenotes/")}>{t.cenotes.tag}</a></li>}
          </ul></div>
          <div><h4>{t.footer.learn}</h4><ul>
            {PRODUCTS.filter((p) => p.kind === "course").map((p) => (
              <li key={p.slug}><a href={`${home}#products`}>{t.products.items[p.slug].name}</a></li>
            ))}
            <li><a href={`${home}#included`}>{t.nav.included}</a></li>
            <li><a href={`${home}#faq`}>{t.nav.faq}</a></li>
          </ul></div>
          <div><h4>{t.footer.find}</h4><ul>
            <li><a href={SHOP.instagram} rel="noopener">@kaydivingtulum</a></li>
            <li><a href={`tel:${SHOP.phone}`}>{SHOP.phoneDisplay}</a></li>
            <li><a href={`https://wa.me/${SHOP.phone.replace(/\D/g, "")}`} rel="noopener">{t.footer.whatsapp}</a></li>
            <li><a href={`mailto:${SHOP.email}`}>{SHOP.email}</a></li>
            <li><span className="muted" style={{ fontSize: ".9rem" }}>{t.footer.meet}: {SHOP.meetingPoint}</span></li>
            <li><span className="muted" style={{ fontSize: ".9rem" }}>{t.footer.place}</span></li>
          </ul></div>
        </div>
        <div className="foot__base data">
          <span>© {new Date().getFullYear()} Kay Diving · Tulum</span>
          <span>{LIVE_LOCALES.map((l) => l.toUpperCase()).join(" · ")}</span>
        </div>
      </div>
    </footer>
  );
}
