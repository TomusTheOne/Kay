/* ============================================================================
   KAY DIVING — Concept A · "XIBALBA"
   Scroll is a descent. Everything else is kept deliberately cheap.
   ========================================================================== */
(() => {
  "use strict";
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const calm = matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ------------------------------------------------------------- Descent -- */
  const MAX_DEPTH = 40;
  const ZONES = [[6,"Surface"],[18,"Cavern zone"],[30,"Deep"],[99,"The abyss"]];
  const depthEl = $("[data-depth]"), zoneEl = $("[data-zone]"), surface = $(".surface");
  let ticking = false;

  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const max = document.documentElement.scrollHeight - innerHeight;
      const p = max > 0 ? Math.min(1, Math.max(0, scrollY / max)) : 0;
      document.documentElement.style.setProperty("--descent", p.toFixed(4));
      const d = Math.round(p * MAX_DEPTH);
      if (depthEl && depthEl.textContent !== String(d)) depthEl.textContent = d;
      if (zoneEl) {
        const z = ZONES.find(([lim]) => d < lim)[1];
        if (zoneEl.textContent !== z) zoneEl.textContent = z;
      }
      if (surface) surface.classList.toggle("is-deep", scrollY > 30);
      ticking = false;
    });
  }
  addEventListener("scroll", onScroll, { passive: true });
  addEventListener("resize", onScroll, { passive: true });
  onScroll();

  /* ----------------------------------------------------------------- Nav -- */
  const burger = $(".burger");
  if (burger) {
    burger.addEventListener("click", () => {
      const open = document.body.classList.toggle("open");
      burger.setAttribute("aria-expanded", String(open));
      $(".drawer").setAttribute("aria-hidden", String(!open));
    });
    $$(".drawer a").forEach(a => a.addEventListener("click", () => {
      document.body.classList.remove("open");
      burger.setAttribute("aria-expanded", "false");
    }));
  }
  addEventListener("keydown", e => { if (e.key === "Escape") document.body.classList.remove("open"); });

  $$(".lang button").forEach(b => b.addEventListener("click", () => {
    $$(".lang button").forEach(o => o.classList.remove("on"));
    b.classList.add("on");
  }));

  /* -------------------------------------------------------------- Reveal -- */
  const rise = $$("[data-rise]");
  if (calm || !("IntersectionObserver" in window)) {
    rise.forEach(el => el.classList.add("in"));
  } else {
    const io = new IntersectionObserver(es => es.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
    }), { threshold: 0.1, rootMargin: "0px 0px -6% 0px" });
    rise.forEach(el => io.observe(el));
  }

  /* ------------------------------------------------------------- Booking -- */
  const form = $("#bookForm");
  if (!form) return;

  const dateEl = $("#date");
  if (dateEl) {
    const t = new Date(Date.now() + 864e5);
    dateEl.min = t.toISOString().slice(0, 10);
  }

  let divers = 2;
  const diversOut = $("#divers");
  $$("[data-step] button").forEach(b => b.addEventListener("click", () => {
    divers = Math.min(8, Math.max(1, divers + Number(b.dataset.d)));
    diversOut.textContent = divers;
    render();
  }));

  const money = n => "$" + n.toLocaleString("en-US");
  const PER_DIVER = new Set(["Nitrox", "Third dive"]);

  function render() {
    const exp = form.querySelector('input[name="exp"]:checked');
    const pick = $("#pickup").selectedOptions[0];
    const adds = $$('input[name="add"]:checked');

    const base = Number(exp.dataset.price) * divers;
    const pickup = Number(pick.dataset.price || 0);
    const extras = adds.reduce((sum, a) =>
      sum + Number(a.dataset.price) * (PER_DIVER.has(a.value) ? divers : 1), 0);

    $("#s-exp").textContent    = exp.value;
    $("#s-depth").textContent  = exp.dataset.depth + " m";
    $("#s-level").textContent  = exp.dataset.level;
    $("#s-divers").textContent = divers;
    $("#s-pickup").textContent = pick.text.split(" — ")[0].split(" · ")[0].replace(/ \$\d+$/, "");
    $("#s-add").textContent    = adds.length ? adds.map(a => a.value).join(", ") : "None";

    const d = dateEl.value;
    $("#s-date").textContent = d
      ? new Date(d + "T00:00:00").toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })
      : "—";

    $("#s-total").textContent = money(base + pickup + extras);
  }

  form.addEventListener("input", render);
  form.addEventListener("change", render);
  form.addEventListener("submit", e => {
    e.preventDefault();
    $("#slateWrap").classList.add("done");
    $("#slateWrap").scrollIntoView({ behavior: calm ? "auto" : "smooth", block: "center" });
  });
  render();
})();
