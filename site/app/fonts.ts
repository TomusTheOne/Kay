import { Instrument_Serif, Inter, IBM_Plex_Mono } from "next/font/google";

/*
 * The site's three faces, self-hosted at build time: no third-party request,
 * no flash, no layout shift. One module for the locale layout and the 404
 * alike — declared twice, they would each get their own stylesheet, and every
 * page would load two render-blocking CSS files instead of one.
 */
export const display = Instrument_Serif({
  subsets: ["latin"], weight: "400", style: ["normal", "italic"],
  variable: "--font-display", display: "swap",
});
export const body = Inter({
  subsets: ["latin"], weight: ["400", "500", "600"],
  variable: "--font-body", display: "swap",
});
export const mono = IBM_Plex_Mono({
  subsets: ["latin"], weight: ["400", "500", "600"],
  variable: "--font-mono", display: "swap",
});
