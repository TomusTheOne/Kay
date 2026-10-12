import type { Metadata } from "next";
import { Instrument_Serif, IBM_Plex_Mono } from "next/font/google";
import { LIVE_LOCALES, pathFor } from "@/lib/i18n";
import { GUIDE_PUBLISHED } from "@/content/cenotes";
import "./globals.css";

/*
 * The 404 for every URL the site does not have — out/404.html, which
 * .htaccess serves as the ErrorDocument. The root layout is per locale, so
 * this page has none: it states its own fonts, and speaks the three
 * languages at once, since a dead link says nothing about the reader's.
 */
const display = Instrument_Serif({ subsets: ["latin"], weight: "400", variable: "--font-display", display: "swap" });
const mono = IBM_Plex_Mono({ subsets: ["latin"], weight: ["500"], variable: "--font-mono", display: "swap" });

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
    <html lang="en" className={`${display.variable} ${mono.variable}`}>
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
