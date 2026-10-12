import type { Metadata } from "next";
import { LIVE_LOCALES, pathFor } from "@/lib/i18n";
import { GUIDE_PUBLISHED } from "@/content/cenotes";
import { display, body, mono } from "./fonts";
import "./globals.css";

/*
 * The 404 for every URL the site does not have — out/404.html, which
 * .htaccess serves as the ErrorDocument. The root layout is per locale, so
 * this page has none: it takes the fonts from app/fonts.ts, and speaks the three
 * languages at once, since a dead link says nothing about the reader's.
 */

export const metadata: Metadata = {
  title: "Page not found | Kay Diving",
  robots: { index: false, follow: true },
};

const WORDS = {
  en: { lost: "This page is not here.", home: "Home", guide: "Cenote guide" },
  es: { lost: "Esta página no existe.", home: "Inicio", guide: "Guía de cenotes" },
  fr: { lost: "Cette page n'existe pas.", home: "Accueil", guide: "Guide des cenotes" },
} as const;

export default function GlobalNotFound() {
  return (
    <html lang="en" className={`${display.variable} ${body.variable} ${mono.variable}`}>
      <body>
        <main className="bay shell" style={{ minHeight: "80vh", display: "grid", alignContent: "center", gap: "2rem" }}>
          <p className="tag">404</p>
          {LIVE_LOCALES.map((l, i) => {
            const H = i === 0 ? "h1" : "h2";
            return (
            <section key={l} lang={l}>
              <H className="dsp dsp-md" style={{ marginBottom: ".9rem" }}>{WORDS[l].lost}</H>
              <p className="data" style={{ display: "flex", gap: "1.4rem", flexWrap: "wrap" }}>
                <a href={pathFor(l)}>{WORDS[l].home} →</a>
                {GUIDE_PUBLISHED && <a href={pathFor(l, "/cenotes/")}>{WORDS[l].guide} →</a>}
              </p>
            </section>
            );
          })}
        </main>
      </body>
    </html>
  );
}
