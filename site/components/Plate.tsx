/** A photograph in AVIF and WebP, or Kay's line art where there is no photo yet. */
export default function Plate({ photo, art, alt, eager = false }: {
  photo: string | null; art: string; alt: string; eager?: boolean;
}) {
  if (!photo) {
    return <img src={`/assets/art/${art}.svg`} alt={alt}
                loading={eager ? undefined : "lazy"} width={1200} height={800} />;
  }
  return (
    <picture>
      <source srcSet={`/assets/photos/${photo}.avif`} type="image/avif" />
      <source srcSet={`/assets/photos/${photo}.webp`} type="image/webp" />
      <img src={`/assets/photos/${photo}.webp`} alt={alt}
           fetchPriority={eager ? "high" : undefined}
           loading={eager ? undefined : "lazy"} decoding={eager ? undefined : "async"}
           width={1200} height={800} />
    </picture>
  );
}
