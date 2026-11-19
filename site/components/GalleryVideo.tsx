"use client";
import { useEffect, useRef, useState } from "react";

/**
 * A gallery tile that moves: muted, looping, and silent until it is on
 * screen, so the megabyte is only fetched by someone who scrolls that far.
 * It does not start for anyone who asked their system for reduced motion,
 * and the button stops it, because anything that moves for longer than five
 * seconds has to be stoppable.
 */
export default function GalleryVideo({ src, poster, label, play, pause }: {
  src: string; poster: string; label: string; play: string; pause: string;
}) {
  const ref = useRef<HTMLVideoElement>(null);
  const [playing, setPlaying] = useState(false);

  useEffect(() => {
    const video = ref.current;
    if (!video || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    const seen = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return;
      seen.disconnect();
      video.play().then(() => setPlaying(true)).catch(() => {});
    }, { rootMargin: "200px" });
    seen.observe(video);
    return () => seen.disconnect();
  }, []);

  function toggle() {
    const video = ref.current;
    if (!video) return;
    if (video.paused) video.play().then(() => setPlaying(true)).catch(() => {});
    else { video.pause(); setPlaying(false); }
  }

  return (
    <>
      <video ref={ref} src={src} poster={poster} muted loop playsInline preload="none"
             aria-label={label} width={540} height={960} />
      <button type="button" className="gal__play" onClick={toggle} aria-label={playing ? pause : play}>
        <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
          {playing
            ? <path d="M3 2h2v8H3zM7 2h2v8H7z" fill="currentColor" />
            : <path d="M3 1.5l7 4.5-7 4.5z" fill="currentColor" />}
        </svg>
      </button>
    </>
  );
}
