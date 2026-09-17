"use client";
import { useEffect } from "react";

/**
 * One observer for every [data-rise] on the page. The markup is server
 * rendered and visible by default if this never runs — the animation is an
 * enhancement, not a prerequisite for reading the page.
 */
export default function Reveals() {
  useEffect(() => {
    const els = Array.from(document.querySelectorAll<HTMLElement>("[data-rise]"));
    if (!els.length) return;

    if (matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) {
      els.forEach((el) => el.classList.add("in"));
      return;
    }
    const io = new IntersectionObserver(
      (entries) => entries.forEach((e) => {
        if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
      }),
      { threshold: 0.1, rootMargin: "0px 0px -6% 0px" },
    );
    els.forEach((el) => io.observe(el));
    return () => io.disconnect();
  }, []);

  return null;
}
