/**
 * One custom event, sent to whichever provider is configured — or to nobody.
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
