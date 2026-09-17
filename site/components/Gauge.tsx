"use client";
import { useEffect, useState } from "react";

const MAX_DEPTH = 40;

/** Scroll is a descent: the gauge counts metres and the water darkens with it. */
export default function Gauge({ zones }: { zones: [number, string][] }) {
  const [depth, setDepth] = useState(0);

  useEffect(() => {
    let ticking = false;
    const onScroll = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        const max = document.documentElement.scrollHeight - innerHeight;
        const p = max > 0 ? Math.min(1, Math.max(0, scrollY / max)) : 0;
        document.documentElement.style.setProperty("--descent", p.toFixed(4));
        setDepth(Math.round(p * MAX_DEPTH));
        document.querySelector(".surface")?.classList.toggle("is-deep", scrollY > 30);
        ticking = false;
      });
    };
    addEventListener("scroll", onScroll, { passive: true });
    addEventListener("resize", onScroll, { passive: true });
    onScroll();
    return () => { removeEventListener("scroll", onScroll); removeEventListener("resize", onScroll); };
  }, []);

  const zone = zones.find(([limit]) => depth < limit)?.[1] ?? zones[zones.length - 1][1];

  return (
    <aside className="gauge" aria-hidden="true">
      <div className="gauge__rail">
        {[0, 25, 50, 75, 100].map((p, i) => (
          <i key={p} className="gauge__tick" style={{ "--p": p } as React.CSSProperties}>
            <span>{i * 10}</span>
          </i>
        ))}
        <div className="gauge__marker">
          <span className="gauge__dot" />
          <b className="gauge__num">{depth}</b><i className="gauge__u">m</i>
        </div>
        <span className="gauge__cap">{zone}</span>
      </div>
    </aside>
  );
}
