/* Kay Diving — admin.
   Everything here is an enhancement: every page works with it switched off.
   Text from the database is only ever inserted with textContent. */
(() => {
  "use strict";

  // Destructive buttons ask first.
  document.addEventListener("click", (e) => {
    const el = e.target.closest("[data-confirm]");
    if (el && !window.confirm(el.getAttribute("data-confirm"))) e.preventDefault();
  });

  // Filters apply as soon as they change.
  document.addEventListener("change", (e) => {
    const el = e.target.closest("[data-autosubmit]");
    if (!el || !el.form) return;
    if (el.form.requestSubmit) el.form.requestSubmit();
    else el.form.submit();
  });

  document.querySelectorAll("[data-print]").forEach((b) => b.addEventListener("click", () => window.print()));

  /* -------------------------------------------------------- chart tooltips --
     Value first, label second: the reader already knows which chart it is. */
  const tip = document.getElementById("tip");
  if (tip) {
    const show = (col, x, y) => {
      tip.replaceChildren();
      const value = document.createElement("strong");
      value.textContent = col.dataset.tipValue || "";
      const title = document.createElement("span");
      title.textContent = col.dataset.tipTitle || "";
      tip.append(value, title);
      if (col.dataset.tipExtra) {
        const extra = document.createElement("span");
        extra.textContent = " · " + col.dataset.tipExtra;
        tip.append(extra);
      }
      tip.hidden = false;
      const r = tip.getBoundingClientRect();
      const left = Math.min(Math.max(8, x - r.width / 2), window.innerWidth - r.width - 8);
      const top = y - r.height - 12 < 8 ? y + 16 : y - r.height - 12;
      tip.style.left = left + "px";
      tip.style.top = top + "px";
    };
    const hide = () => { tip.hidden = true; };

    document.addEventListener("pointermove", (e) => {
      const col = e.target.closest && e.target.closest(".chart__col");
      if (col) show(col, e.clientX, e.clientY); else hide();
    });
    document.addEventListener("focusin", (e) => {
      const col = e.target.closest && e.target.closest(".chart__col");
      if (!col) return;
      const r = col.getBoundingClientRect();
      show(col, r.left + r.width / 2, r.top + r.height / 3);
    });
    document.addEventListener("focusout", hide);
    window.addEventListener("scroll", hide, { passive: true });
  }

  /* ---------------------------------------------------------- price preview --
     A preview only. The server re-quotes from products.json on save. */
  const out = document.querySelector("[data-quote]");
  const data = document.getElementById("kay-prices");
  if (out && data) {
    let prices;
    try { prices = JSON.parse(data.textContent); } catch { prices = null; }
    const form = out.closest("form");
    const fmt = new Intl.NumberFormat("fr-FR");
    const update = () => {
      if (!prices || !form) return;
      const item = form.querySelector("[data-quote-item]");
      const divers = form.querySelector("[data-quote-divers]");
      const pickup = form.querySelector("[data-quote-pickup]");
      const each = prices.items[item && item.value];
      const n = Math.max(1, Math.min(8, parseInt(divers && divers.value, 10) || 1));
      if (each === undefined) { out.textContent = ""; return; }
      const total = each * n + (prices.pickups[pickup && pickup.value] || 0);
      out.textContent = "Prix du catalogue : " + fmt.format(total) + " MXN";
    };
    if (form) {
      form.addEventListener("input", update);
      form.addEventListener("change", update);
    }
    update();
  }
})();
