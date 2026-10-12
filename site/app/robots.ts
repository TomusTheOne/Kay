import type { MetadataRoute } from "next";
import { SHOP } from "@/content/products";

/**
 * The answer engines, named. "*" already lets them in; naming them says it
 * on purpose, so a later default that turns one away does not quietly take
 * the guide out of ChatGPT, Claude, Perplexity or Gemini answers. Each one
 * reads only its own group, so every group repeats the /api/ rule.
 */
const ANSWER_ENGINES = [
  "GPTBot", "OAI-SearchBot", "ChatGPT-User",
  "ClaudeBot", "Claude-SearchBot", "Claude-User",
  "PerplexityBot", "Perplexity-User",
  "Google-Extended", "Applebot-Extended", "DuckAssistBot", "CCBot",
];

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        // Only the endpoints. The payment outcome pages are NOT listed here on
        // purpose: they already carry <meta robots="noindex">, and a path
        // blocked in robots.txt is never fetched, so that noindex would never
        // be read. Blocking and noindexing the same URL cancels the noindex.
        disallow: ["/api/"],
      },
      { userAgent: ANSWER_ENGINES, allow: "/", disallow: ["/api/"] },
    ],
    sitemap: `${SHOP.domain}/sitemap.xml`,
  };
}

export const dynamic = "force-static";
