/* ============================================================================
   KAY DIVING — Concept B · "LUZ"
   Deliberately small: a menu, one observer, and the reservation maths.
   ========================================================================== */
(() => {
  "use strict";
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const calm = matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------------------------------------------------------------- Menu -- */
  const burger = $(".mburger");
  if (burger) {
    burger.addEventListener("click", () => {
      const open = document.body.classList.toggle("nav-open");
      burger.setAttribute("aria-expanded", String(open));
      $(".msheet").setAttribute("aria-hidden", String(!open));
    });
    $$(".msheet a").forEach(a => a.addEventListener("click", () => {
      document.body.classList.remove("nav-open");
      burger.setAttribute("aria-expanded", "false");
    }));
  }
  addEventListener("keydown", e => { if (e.key === "Escape") document.body.classList.remove("nav-open"); });

  $$(".langs button").forEach(b => b.addEventListener("click", () => {
    $$(".langs button").forEach(o => o.classList.remove("on"));
    b.classList.add("on");
  }));

  /* -------------------------------------------------------------- Reveal -- */
  const ups = $$("[data-up]");
  if (calm || !("IntersectionObserver" in window)) {
    ups.forEach(el => el.classList.add("in"));
  } else {
    const io = new IntersectionObserver(es => es.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
    }), { threshold: 0.08, rootMargin: "0px 0px -5% 0px" });
    ups.forEach(el => io.observe(el));
  }

  /* --------------------------------------------------------- Reservation -- */
  const form = $("#resaForm");
  if (!form) return;

  const date = $("#b-date");
  date.min = new Date(Date.now() + 864e5).toISOString().slice(0, 10);

  let n = 2;
  $$("[data-count] button").forEach(b => b.addEventListener("click", () => {
    n = Math.min(8, Math.max(1, n + Number(b.dataset.d)));
    $("#b-n").textContent = n;
    draw();
  }));

  function draw() {
    const dive = form.querySelector('input[name="dive"]:checked');
    const pick = $("#b-pick").selectedOptions[0];
    const xs   = $$('input[name="x"]:checked');

    const total =
      Number(dive.dataset.price) * n +
      Number(pick.dataset.price || 0) +
      xs.reduce((s, x) => s + Number(x.dataset.price) * (x.hasAttribute("data-each") ? n : 1), 0);

    $("#r-dive").textContent  = dive.value;
    $("#r-depth").textContent = dive.dataset.depth;
    $("#r-lvl").textContent   = dive.dataset.lvl;
    $("#r-n").textContent     = n;
    $("#r-pick").textContent  = pick.text.split(" — ")[0];
    $("#r-x").textContent     = xs.length ? xs.map(x => x.value).join(", ") : "None";
    $("#r-date").textContent  = date.value
      ? new Date(date.value + "T00:00:00").toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })
      : "—";
    $("#r-tot").textContent   = "$" + total.toLocaleString("en-US");
  }

  form.addEventListener("input", draw);
  form.addEventListener("change", draw);
  form.addEventListener("submit", e => {
    e.preventDefault();
    $("#resa").classList.add("sent");
    $("#resa").scrollIntoView({ behavior: calm ? "auto" : "smooth", block: "center" });
  });
  draw();
})();
