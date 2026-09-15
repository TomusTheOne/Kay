/* ============================================================================
   KAY DIVING — Concept C · "BITÁCORA"
   Site filtering, the log-entry maths, and nothing else.
   ========================================================================== */
(() => {
  "use strict";
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const calm = matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------------------------------------------------------------- Menu -- */
  const bgr = $(".bgr");
  if (bgr) {
    bgr.addEventListener("click", () => {
      const open = document.body.classList.toggle("on");
      bgr.setAttribute("aria-expanded", String(open));
      $(".sheet").setAttribute("aria-hidden", String(!open));
    });
    $$(".sheet a").forEach(a => a.addEventListener("click", () => {
      document.body.classList.remove("on");
      bgr.setAttribute("aria-expanded", "false");
    }));
  }
  addEventListener("keydown", e => { if (e.key === "Escape") document.body.classList.remove("on"); });

  $$(".lgs").forEach(group => $$("button", group).forEach(b =>
    b.addEventListener("click", () => {
      $$(".lgs button").forEach(o => o.classList.remove("on"));
      $$(".lgs").forEach(g => {
        const m = $$("button", g).find(x => x.textContent === b.textContent);
        if (m) m.classList.add("on");
      });
    })));

  /* -------------------------------------------------------------- Reveal -- */
  const ins = $$("[data-in]");
  if (calm || !("IntersectionObserver" in window)) {
    ins.forEach(el => el.classList.add("vis"));
  } else {
    const io = new IntersectionObserver(es => es.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add("vis"); io.unobserve(e.target); }
    }), { threshold: 0.06, rootMargin: "0px 0px -4% 0px" });
    ins.forEach(el => io.observe(el));
  }

  /* -------------------------------------------------------- Site filtering */
  const sites = $$(".site");
  const none  = $("#noSites");
  $$(".chip").forEach(chip => chip.addEventListener("click", () => {
    $$(".chip").forEach(c => c.classList.remove("on"));
    chip.classList.add("on");
    const f = chip.dataset.filter;
    let shown = 0;
    sites.forEach(s => {
      const hit = f === "all" || s.dataset.level === f || s.dataset.type === f;
      s.hidden = !hit;
      if (hit) shown++;
    });
    if (none) none.hidden = shown > 0;
  }));

  /* ----------------------------------------------------------- Log entry -- */
  const form = $("#logForm");
  if (!form) return;

  const date = $("#c-date");
  date.min = new Date(Date.now() + 864e5).toISOString().slice(0, 10);

  let n = 2;
  $$("[data-ctr] button").forEach(b => b.addEventListener("click", () => {
    n = Math.min(8, Math.max(1, n + Number(b.dataset.d)));
    $("#c-n").textContent = n;
    draw();
  }));

  function draw() {
    const site = form.querySelector('input[name="site"]:checked');
    const pick = $("#c-pick").selectedOptions[0];
    const ads  = $$('input[name="ad"]:checked');

    const total =
      Number(site.dataset.price) * n +
      Number(pick.dataset.price || 0) +
      ads.reduce((s, a) => s + Number(a.dataset.price) * (a.hasAttribute("data-each") ? n : 1), 0);

    $("#l-site").textContent  = site.value;
    $("#l-depth").textContent = site.dataset.depth;
    $("#l-lvl").textContent   = site.dataset.lvl;
    $("#l-n").textContent     = n;
    $("#l-pick").textContent  = pick.text.split(" — ")[0];
    $("#l-ad").textContent    = ads.length ? ads.map(a => a.value).join(", ") : "None";
    $("#l-date").textContent  = date.value
      ? new Date(date.value + "T00:00:00").toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })
      : "——";
    $("#l-tot").textContent   = "$" + total.toLocaleString("en-US");
  }

  form.addEventListener("input", draw);
  form.addEventListener("change", draw);
  form.addEventListener("submit", e => {
    e.preventDefault();
    $("#log").classList.add("sent");
    $("#log").scrollIntoView({ behavior: calm ? "auto" : "smooth", block: "center" });
  });
  draw();
})();
