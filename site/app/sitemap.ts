import type { MetadataRoute } from "next";
import { LOCALES, pathFor } from "@/lib/i18n";
import { SHOP } from "@/content/products";

/** One entry per locale, each declaring its siblings so Google pairs them. */
export default function sitemap(): MetadataRoute.Sitemap {
  const languages = Object.fromEntries(
    LOCALES.map((l) => [l, `${SHOP.domain}${pathFor(l)}`]),
  );

  return LOCALES.map((locale) => ({
    url: `${SHOP.domain}${pathFor(locale)}`,
    lastModified: new Date(),
    changeFrequency: "monthly" as const,
    priority: locale === "en" ? 1 : 0.8,
    alternates: { languages },
  }));
}

export const dynamic = "force-static";
