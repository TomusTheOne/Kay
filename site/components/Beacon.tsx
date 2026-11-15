"use client";
import { useEffect } from "react";
import { usePathname } from "next/navigation";
import { beacon } from "@/lib/analytics";

/**
 * Kay's own traffic count: one beacon per page, and one when the booking
 * form comes into view.
 *
 * That second event is the middle of the funnel. The site is one long page
 * per language, so "viewed a page" says little; "scrolled far enough to see
 * the booking form" separates the people who looked at the photographs from
 * the people who were considering a date. With checkout-opened, fired by the
 * form itself, and the deposits in the bookings table, the admin lines up
 * four numbers: visitors, saw the form, opened the payment page, paid.
 *
 * Renders nothing, and costs nothing before the page is interactive.
 */
export default function Beacon() {
  const pathname = usePathname();

  /* A partner's link — kaydiving.com/es/?ref=jashita — is remembered the
     moment it is opened, on whatever page it lands. Concierges hand out a
     link to the experience the guest asked about, not to the booking form,
     so by the time the form is reached the query string is long gone and
     the guest is left trying to recall a hotel's name. Session storage, not
     a cookie: it belongs to this visit, it never leaves the browser, and it
     is read by one field. */
  useEffect(() => {
    try {
      const ref = new URLSearchParams(window.location.search).get("ref");
      if (ref) sessionStorage.setItem("kay_ref", ref.slice(0, 64));
    } catch { /* private window, storage disabled: the field just stays empty */ }
  }, [pathname]);

  useEffect(() => {
    beacon("pageview");

    const form = document.getElementById("book");
    if (!form || !("IntersectionObserver" in window)) return;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting)) {
          beacon("booking-viewed");
          observer.disconnect();
        }
      },
      { threshold: 0.25 },
    );
    observer.observe(form);
    return () => observer.disconnect();
  }, [pathname]);

  return null;
}
