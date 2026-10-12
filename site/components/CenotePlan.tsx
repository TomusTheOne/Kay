import type { Locale } from "@/lib/i18n";
import { mapFile, type Cenote } from "@/content/cenotes";

/**
 * The dive map of one cenote: a plan seen from above, or a section for the
 * deep ones. One image per language, since the labels are part of it, in
 * AVIF and WebP at three widths. A portrait plan is held to 1200 px of
 * height, so its labels stay as large as a landscape one's. The figure opens
 * the largest file on a tap, which is how a phone gets to read the depths.
 */
export default function CenotePlan({ c, plan, locale, alt, open }: {
  c: Cenote; plan: NonNullable<Cenote["plan"]>; locale: Locale; alt: string; open: string;
}) {
  const src = (w: number, ext: "webp" | "avif") => mapFile(c, locale, w, ext);
  const widths = [800, plan.w, plan.w * 2];
  const set = (ext: "webp" | "avif") => widths.map((w) => `${src(w, ext)} ${w}w`).join(", ");
  const ar = (plan.w / plan.h).toFixed(3);
  const sizes = `min(92vw, 1220px, ${Math.round(Number(ar) * 1200)}px)`;
  return (
    <a className="plan" href={src(plan.w * 2, "webp")} target="_blank" rel="noopener"
       style={{ "--ar": ar } as React.CSSProperties}>
      <picture>
        <source type="image/avif" srcSet={set("avif")} sizes={sizes} />
        <source type="image/webp" srcSet={set("webp")} sizes={sizes} />
        <img src={src(plan.w, "webp")} alt={alt} width={plan.w} height={plan.h} loading="lazy" decoding="async" />
      </picture>
      <span className="plan__open data">{open} ↗</span>
    </a>
  );
}
