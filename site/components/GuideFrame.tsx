import Surface from "@/components/Surface";
import Footer from "@/components/Footer";
import { pathFor, type Dictionary, type Locale } from "@/lib/i18n";
import { GUIDE_PUBLISHED } from "@/content/cenotes";

/**
 * Header, preview notice and footer around every page of the cenote guide.
 * The menu is the home page's, pointing back at its sections.
 */
export default function GuideFrame({ t, locale, path, children }: {
  t: Dictionary; locale: Locale; path: string; children: React.ReactNode;
}) {
  const home = pathFor(locale);
  const nav = [
    { href: `${home}#about`, label: t.nav.about },
    { href: `${home}#products`, label: t.nav.products },
    { href: pathFor(locale, "/cenotes/"), label: t.nav.cenotes },
    { href: `${home}#included`, label: t.nav.included },
    { href: `${home}#logistics`, label: t.nav.logistics },
    { href: `${home}#faq`, label: t.nav.faq },
  ];
  return (
    <>
      <Surface locale={locale} nav={nav} reserve={t.nav.reserve} reserveLong={t.nav.reserveLong}
               menuLabel={t.nav.menu} langLabel={t.nav.language} path={path} scrolls />
      <main id="main" className="guide">
        {!GUIDE_PUBLISHED && <p className="guide__preview data" role="note">{t.cenotes.preview}</p>}
        {children}
      </main>
      <Footer t={t} locale={locale} home={home} />
    </>
  );
}
