import type { Metadata } from "next";
import { Instrument_Serif, Inter, IBM_Plex_Mono } from "next/font/google";
import { notFound } from "next/navigation";
import { LIVE_LOCALES, DEFAULT_LOCALE, OG_LOCALE, isLocale, getDictionary, pathFor, type Locale } from "@/lib/i18n";
import { SHOP } from "@/content/products";
import Analytics from "@/components/Analytics";
import "../globals.css";

/* Self-hosted at build time: no third-party request, no flash, no layout shift. */
const display = Instrument_Serif({
  subsets: ["latin"], weight: "400", style: ["normal", "italic"],
  variable: "--font-display", display: "swap",
});
const body = Inter({
  subsets: ["latin"], weight: ["400", "500", "600"],
  variable: "--font-body", display: "swap",
});
const mono = IBM_Plex_Mono({
  subsets: ["latin"], weight: ["400", "500", "600"],
  variable: "--font-mono", display: "swap",
});

/** Only the translated locales are built at all — see LIVE_LOCALES. */
export function generateStaticParams() {
  return LIVE_LOCALES.map((locale) => ({ locale }));
}

export async function generateMetadata(
  { params }: { params: Promise<{ locale: string }> },
): Promise<Metadata> {
  const { locale } = await params;
  if (!isLocale(locale)) return {};
  const t = await getDictionary(locale);
  const url = `${SHOP.domain}${pathFor(locale)}`;

  return {
    metadataBase: new URL(SHOP.domain),
    title: t.meta.title,
    description: t.meta.description,
    alternates: {
      canonical: url,
      languages: {
        ...Object.fromEntries(LIVE_LOCALES.map((l) => [l, `${SHOP.domain}${pathFor(l)}`])),
        "x-default": `${SHOP.domain}${pathFor(DEFAULT_LOCALE)}`,
      },
    },
    openGraph: {
      type: "website", siteName: "Kay Diving Tulum", url,
      title: t.meta.title, description: t.meta.description,
      locale: OG_LOCALE[locale],
      alternateLocale: LIVE_LOCALES.filter((l) => l !== locale).map((l) => OG_LOCALE[l]),
      // Built by scripts/og.mjs. A real 1200×630 JPEG, because the crawlers
      // ask for that size and WhatsApp — where these links actually travel —
      // is unreliable with WebP.
      images: [{
        url: `/assets/og/${locale}.jpg`, width: 1200, height: 630,
        type: "image/jpeg", alt: t.hero.alt,
      }],
    },
    twitter: { card: "summary_large_image", images: [`/assets/og/${locale}.jpg`] },
    /* Search Console's meta-tag method, for whoever prefers it to a DNS
       record. Prefer the DNS TXT record: it verifies the whole domain in one
       property — apex, www, http and https, all three languages — and it
       cannot be undone by a deploy. This tag only ever verifies the exact URL
       it sits on, and "/" here is a 302 to "/en/", so a URL-prefix property on
       the apex has a redirect in the way. */
    verification: process.env.NEXT_PUBLIC_GSC_VERIFICATION
      ? { google: process.env.NEXT_PUBLIC_GSC_VERIFICATION }
      : undefined,
    robots: {
      index: true, follow: true,
      googleBot: { index: true, follow: true, "max-image-preview": "large",
                   "max-snippet": -1, "max-video-preview": -1 },
    },
    icons: {
      // Kay's own kit. The .ico carries 16/32/48/64 for the tab, the SVG lets
      // a modern browser scale it, and the emblem is cropped to the helmet
      // below 48px — the full line art is unreadable at tab size.
      icon: [
        { url: "/favicon.ico", sizes: "any" },
        { url: "/assets/brand/favicon.svg", type: "image/svg+xml" },
      ],
      apple: "/assets/brand/apple-touch-icon.png",
    },
    manifest: "/site.webmanifest",
  };
}

export const viewport = { themeColor: "#03090E" };

export default async function LocaleLayout({
  children, params,
}: { children: React.ReactNode; params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();

  return (
    <html lang={locale} className={`${display.variable} ${body.variable} ${mono.variable}`}>
      <body>
        {children}
        <Analytics />
      </body>
    </html>
  );
}

export type { Locale };
