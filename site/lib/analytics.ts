/**
 * One custom event, sent to Kay's own server and to whichever third-party
 * provider is configured — usually none.
 *
 * The point is the funnel. A pageview count on its own says how many people
 * looked; it does not say how many got as far as handing over a card. With
 * "checkout-opened" fired here and the pageview that /booking/thanks/ already
 * produces on the way back from Mercado Pago, the three numbers line up:
 * visitors, checkouts opened, deposits paid — per country, which is the whole
 * reason Kay wants any of this.
 *
 * Nothing here identifies anyone. The properties are what was booked, never
 * who booked it: no name, no email, nothing the booking row does not already
 * hold under Kay's own control.
 */

type Props = Record<string, string | number>;

declare global {
  interface Window {
    umami?: { track: (name: string, data?: Props) => unknown };
    plausible?: (name: string, opts?: { props?: Props; callback?: () => void }) => void;
  }
}

const PROVIDER = (process.env.NEXT_PUBLIC_ANALYTICS_PROVIDER ?? "").trim();

/* ------------------------------------------------------ first-party beacon --
   Always on, because it is Kay's own server counting its own pages — no third
   party, no cookie, nothing stored on the device. What the server keeps, and
   what it deliberately does not, is in php/lib/traffic.php; the result is the
   Traffic page of the admin.

   sendBeacon, because it survives the page being left: the one event that
   matters most, checkout-opened, fires an instant before location.href sends
   the diver to Mercado Pago. It is fire-and-forget, so nothing here ever
   waits on it.                                                              */

const BEACON_URL = "/api/track.php";

/** The previous page of this site, so a click between two pages is not an arrival. */
let previousUrl: string | null = null;

export function beacon(event: string, props: Props = {}): void {
  // `next dev` has no PHP behind it: a failed request per page would only
  // be noise in the console.
  if (typeof window === "undefined" || process.env.NODE_ENV !== "production") return;

  try {
    const q = new URLSearchParams(location.search);
    const body = JSON.stringify({
      e: event,
      p: location.pathname,
      r: event === "pageview" ? (previousUrl ?? document.referrer) : "",
      tz: Intl.DateTimeFormat().resolvedOptions().timeZone ?? "",
      l: navigator.language ?? "",
      // iPadOS says it is a Mac; a Mac with a touch screen is an iPad.
      t: navigator.maxTouchPoints > 1 ? 1 : 0,
      us: q.get("utm_source") ?? q.get("ref") ?? "",
      um: q.get("utm_medium") ?? "",
      uc: q.get("utm_campaign") ?? "",
      pr: props.product ?? "",
    });
    if (event === "pageview") previousUrl = location.href;

    if (!navigator.sendBeacon?.(BEACON_URL, body)) {
      fetch(BEACON_URL, { method: "POST", body, keepalive: true }).catch(() => {});
    }
  } catch {
    // Counting a visit is never worth an error in front of the visitor.
  }
}

/**
 * A beacon must never be the reason someone waits to reach the payment page,
 * and a redirect must never be the reason the beacon is lost. So: send it,
 * and continue on whichever comes first — the provider's acknowledgement or
 * this deadline.
 */
const DEADLINE_MS = 400;

/**
 * Resolves when the event is away, or when the deadline passes, whichever is
 * first. Never rejects: an analytics failure must not cost a booking, so every
 * path out of here is a resolve.
 */
export function track(name: string, props: Props = {}): Promise<void> {
  // Kay's own count first: synchronous, and it outlives the redirect.
  beacon(name, props);
  if (typeof window === "undefined" || !PROVIDER) return Promise.resolve();

  return new Promise<void>((resolve) => {
    // Whatever happens below, the caller is released by the deadline.
    const timer = setTimeout(resolve, DEADLINE_MS);
    const done = () => {
      clearTimeout(timer);
      resolve();
    };

    try {
      if (PROVIDER === "umami" && window.umami) {
        // v2 returns a promise; older builds return undefined, hence the guard.
        const sent = window.umami.track(name, props);
        if (sent instanceof Promise) {
          sent.then(done, done);
        } else {
          done();
        }
        return;
      }

      if (PROVIDER === "plausible" && window.plausible) {
        window.plausible(name, { props, callback: done });
        return;
      }

      // Cloudflare Web Analytics counts pageviews only — it has no custom
      // events — and an unconfigured or blocked provider leaves no global
      // behind. Both are normal, and neither is worth a delay.
      done();
    } catch {
      done();
    }
  });
}
