/**
 * The analytics beacon, or nothing at all.
 *
 * Nothing is the default and it is not a placeholder: with no provider
 * configured this component renders null, so a build that nobody has
 * configured ships zero third-party requests rather than a dead script tag.
 *
 * Why not Google Analytics. The site's stated order is design, then speed,
 * then SEO. GA4 costs about 90 KB of JavaScript against a page that currently
 * loads none from third parties, and its cookies oblige a consent banner for
 * the European half of Kay's customers — a banner over the hero, on the one
 * screen the whole design is built around. The providers below are cookieless
 * and about 1 KB, which is why they need no banner at all.
 *
 * All three are configured the same way, from build-time environment
 * variables, so switching provider is a GitHub variable and a redeploy — no
 * code change, no lost history on the side that keeps it.
 */

type Provider = "umami" | "plausible" | "cloudflare";

const PROVIDER = (process.env.NEXT_PUBLIC_ANALYTICS_PROVIDER ?? "").trim() as Provider | "";
const ID = (process.env.NEXT_PUBLIC_ANALYTICS_ID ?? "").trim();
/** Only for a self-hosted instance; each provider's cloud default is below. */
const SRC = (process.env.NEXT_PUBLIC_ANALYTICS_SRC ?? "").trim();

export default function Analytics() {
  if (!PROVIDER || !ID) return null;

  // defer on every one of them: the beacon must never sit in front of the
  // first paint of a page whose whole job is to look good immediately.
  switch (PROVIDER) {
    case "umami":
      return (
        <script
          defer
          src={SRC || "https://cloud.umami.is/script.js"}
          data-website-id={ID}
        />
      );

    case "plausible":
      // ID is the domain as Plausible knows it, e.g. "kaydiving.com".
      return (
        <script defer src={SRC || "https://plausible.io/js/script.js"} data-domain={ID} />
      );

    case "cloudflare":
      // Its beacon wants the token inside a JSON attribute, which is why this
      // one is a string rather than a plain prop.
      return (
        <script
          defer
          src={SRC || "https://static.cloudflareinsights.com/beacon.min.js"}
          data-cf-beacon={JSON.stringify({ token: ID })}
        />
      );

    default:
      // An unknown provider name is a typo in a GitHub variable, and shipping
      // nothing is better than shipping a request to somewhere unintended.
      return null;
  }
}
