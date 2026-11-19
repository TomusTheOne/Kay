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
          {/* Kay's logo, whole: emblem, wordmark, "TULUM - MÉXICO" and the
              fish. Vector (scripts/trace-logo.py), so the tagline stays sharp
              at the 72px it is shown at, and on phones at 50px — below that
              it stops being legible, which is what sets the header's height.
              An <img>, not inlined: 25 KB gzipped of curves has no business
              in every page's HTML when one cached request will do. */}
          <img className="mark__logo" src="/assets/brand/v2/logo.svg"
               width="1380" height="374" alt="Kay Diving · Tulum, México" />
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
