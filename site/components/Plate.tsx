/**
 * A photograph in AVIF and WebP, or Kay's line art where there is no photo yet.
 *
 * With `sizes`, the photo is a card's: scripts/photos.py also cuts it at 480
 * and 800 px, and the browser picks the width the card is shown at instead of
 * the full file. `full` is that full file's width (960 for card-*, 1200 for
 * cenote-*).
 */
export default function Plate({ photo, art, alt, eager = false, sizes, full = 1200 }: {
  photo: string | null; art: string; alt: string; eager?: boolean; sizes?: string; full?: number;
}) {
  if (!photo) {
    return <img src={`/assets/art/${art}.svg`} alt={alt}
                loading={eager ? undefined : "lazy"} width={1200} height={800} />;
  }
  const base = `/assets/photos/${photo}`;
  const set = (ext: string) => sizes
    ? `${base}-480.${ext} 480w, ${base}-800.${ext} 800w, ${base}.${ext} ${full}w`
    : `${base}.${ext}`;
  return (
    <picture>
      <source srcSet={set("avif")} sizes={sizes} type="image/avif" />
      <source srcSet={set("webp")} sizes={sizes} type="image/webp" />
      <img src={`${base}.webp`} alt={alt}
           fetchPriority={eager ? "high" : undefined}
           loading={eager ? undefined : "lazy"} decoding={eager ? undefined : "async"}
           width={1200} height={800} />
    </picture>
  );
}
