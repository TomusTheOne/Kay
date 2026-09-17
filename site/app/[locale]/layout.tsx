import type { Metadata } from "next";
import { Instrument_Serif, Inter, IBM_Plex_Mono } from "next/font/google";
import { notFound } from "next/navigation";
import { LOCALES, isLocale, getDictionary, pathFor, type Locale } from "@/lib/i18n";
import { SHOP } from "@/content/dives";
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

export function generateStaticParams() {
  return LOCALES.map((locale) => ({ locale }));
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
        ...Object.fromEntries(LOCALES.map((l) => [l, `${SHOP.domain}${pathFor(l)}`])),
        "x-default": SHOP.domain,
      },
    },
    openGraph: {
      type: "website", siteName: "Kay Diving Tulum", url,
      title: t.meta.title, description: t.meta.description,
      locale, images: [{ url: "/assets/photos/hero.webp", width: 861, height: 531 }],
    },
    twitter: { card: "summary_large_image" },
    robots: { index: true, follow: true },
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
      <body>{children}</body>
    </html>
  );
}

export type { Locale };
