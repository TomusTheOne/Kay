"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { LIVE_LOCALES, pathFor, type Locale } from "@/lib/i18n";

type Nav = { href: string; label: string }[];

export default function Surface({
  locale, nav, reserve, reserveLong, menuLabel, langLabel, path = "/", scrolls = false,
}: {
  locale: Locale; nav: Nav; reserve: string; reserveLong: string;
  menuLabel: string; langLabel: string;
  /** This page's path below the locale, so the switcher lands on the same page. */
  path?: string;
  /** Pages without the depth gauge darken the bar themselves once scrolled. */
  scrolls?: boolean;
}) {
  const [open, setOpen] = useState(false);
  /* The booking form lives on the home page; elsewhere the button goes there. */
  const book = path === "/" ? "#book" : `${pathFor(locale)}#book`;

  useEffect(() => {
    if (!scrolls) return;
    const onScroll = () => document.querySelector(".surface")?.classList.toggle("is-deep", scrollY > 30);
    addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    return () => removeEventListener("scroll", onScroll);
  }, [scrolls]);

  useEffect(() => {
    document.body.classList.toggle("open", open);
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    addEventListener("keydown", onKey);
    return () => removeEventListener("keydown", onKey);
  }, [open]);

  /* Only the translated locales are offered. A visitor clicking ES and
     landing on English is worse than no switcher at all — and while one
     locale is live there is nothing to switch to. */
  const langs = LIVE_LOCALES.length < 2 ? null : (
    <div className="lang" role="group" aria-label={langLabel}>
      {LIVE_LOCALES.map((l) => (
        <Link key={l} href={pathFor(l, path)} className={l === locale ? "on" : undefined}
              aria-current={l === locale ? "true" : undefined}>
          {l.toUpperCase()}
        </Link>
      ))}
    </div>
  );

  return (
    <>
      <header className="surface">
        <Link className="mark" href={pathFor(locale)} aria-label="Kay Diving">
          {/* Kay's own emblem, two-tone. Not inlined — 21 KB gzipped of traced
              line art has no business in every page's HTML — so it is an
              <img>, one cached request, gzipped by the .htaccess. Its colours
              are baked in by scripts/brand.mjs rather than set in CSS: an SVG
              loaded through <img> is its own document and never sees the
              page's currentColor. Below about 40px the helmet stops reading
              as a helmet, which is why the mark is larger than the bubble it
              replaces. */}
          <img className="mark__ico" src="/assets/brand/icon.svg"
               width="40" height="40" alt="" aria-hidden="true" />
          <span className="mark__txt">
            <b className="mark__name">Kay Diving</b>
            <span className="mark__sub">TULUM · MÉXICO</span>
          </span>
        </Link>

        <nav className="nav" aria-label="Main">
          {nav.map((n) => <a key={n.href} href={n.href}>{n.label}</a>)}
        </nav>

        <div className="surface__side">
          {langs}
          <a className="btn btn--lit btn--sm" href={book}>{reserve}</a>
          <button className="burger" type="button" aria-label={menuLabel}
                  aria-expanded={open} onClick={() => setOpen((v) => !v)}>
            <span /><span /><span />
          </button>
        </div>
      </header>

      <div className="drawer" aria-hidden={!open}>
        {nav.map((n) => (
          <a key={n.href} href={n.href} onClick={() => setOpen(false)}>{n.label}</a>
        ))}
        <a href={book} className="tq" onClick={() => setOpen(false)}>{reserveLong}</a>
        {langs}
      </div>
    </>
  );
}
