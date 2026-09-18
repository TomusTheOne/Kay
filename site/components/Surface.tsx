"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { LIVE_LOCALES, pathFor, type Locale } from "@/lib/i18n";

type Nav = { href: string; label: string }[];

export default function Surface({
  locale, nav, reserve, reserveLong, menuLabel, langLabel,
}: {
  locale: Locale; nav: Nav; reserve: string; reserveLong: string;
  menuLabel: string; langLabel: string;
}) {
  const [open, setOpen] = useState(false);

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
        <Link key={l} href={pathFor(l)} className={l === locale ? "on" : undefined}
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
          <svg className="mark__ico" viewBox="0 0 64 64" aria-hidden="true">
            <defs>
              <linearGradient id="mk" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stopColor="#A9F5EC" /><stop offset=".55" stopColor="#4FE0D2" />
                <stop offset="1" stopColor="#0E3241" />
              </linearGradient>
            </defs>
            <circle cx="32" cy="32" r="19" fill="url(#mk)" />
            <path d="M26 14 L38 14 L44 50 L20 50 Z" fill="#F2FFFC" opacity=".45" />
            <circle cx="32" cy="32" r="19" fill="none" stroke="#03090E" strokeWidth="3" />
            <circle cx="32" cy="32" r="23.5" fill="none" stroke="#4FE0D2" strokeWidth="2" opacity=".5" />
          </svg>
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
          <a className="btn btn--lit btn--sm" href="#book">{reserve}</a>
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
        <a href="#book" className="tq" onClick={() => setOpen(false)}>{reserveLong}</a>
        {langs}
      </div>
    </>
  );
}
