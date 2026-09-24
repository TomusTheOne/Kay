import Link from "next/link";
import { pathFor, type Locale } from "@/lib/i18n";

/** Where Mercado Pago sends the diver back to. Plain, reassuring, and real. */
export default function Outcome({
  locale, tag, h2, p, note, cta, tone = "ok",
}: {
  locale: Locale; tag: string; h2: string; p: string; note?: string; cta: string; tone?: "ok" | "wait" | "bad";
}) {
  return (
    <main className="bay shell" style={{ minHeight: "70svh", display: "grid", placeItems: "center" }}>
      <div style={{ maxWidth: "52ch", textAlign: "center" }}>
        <span
          aria-hidden="true"
          style={{
            display: "grid", placeItems: "center", width: 64, height: 64, margin: "0 auto 1.6rem",
            borderRadius: "50%", border: "1.5px solid var(--turq)", color: "var(--turq)",
            opacity: tone === "bad" ? 0.55 : 1,
          }}
        >
          {tone === "ok" ? (
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
              <path d="M4 12.5l5 5L20 6.5" stroke="currentColor" strokeWidth="1.8"
                    strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          ) : tone === "wait" ? (
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.6" />
              <path d="M12 7v5.5l3.5 2" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            </svg>
          ) : (
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
              <path d="M7 7l10 10M17 7L7 17" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
            </svg>
          )}
        </span>
        <p className="tag tag--plain" style={{ justifyContent: "center" }}>{tag}</p>
        <h1 className="dsp dsp-lg" style={{ marginTop: "1rem" }}>{h2}</h1>
        <p className="lede" style={{ margin: "1.3rem auto 0" }}>{p}</p>
        {/* What the diver must bring. Said here, the moment the booking is
            real, as well as in the confirmation email: the card left at the
            hotel is the one thing that ends a dive day before it starts. */}
        {note && (
          <p className="slate__fine" style={{ margin: "1.2rem auto 0", maxWidth: "52ch", color: "var(--turq)" }}>
            {note}
          </p>
        )}
        <Link className="btn btn--lit" href={pathFor(locale)} style={{ marginTop: "2.2rem" }}>
          {cta}
        </Link>
      </div>
    </main>
  );
}
