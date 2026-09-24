"use client";
import { useEffect, useRef, useState } from "react";
import type * as ML from "maplibre-gl";
import {
  CENOTES, TOWNS, BASEMAP, MAP_BOUNDS, fromTulum, diveDepth, type Cenote,
} from "@/content/cenotes";
import type { MapCopy } from "@/lib/i18n";

/**
 * The cenote map: every cenote Kay dives, on a basemap the site serves itself
 * (public/tiles/, cut by scripts/basemap.mjs) — no tile service, no key, no
 * third party.
 *
 * Nothing loads until the map is about to scroll into view: MapLibre is the
 * heaviest thing on the site, and most visitors never reach it. The markers
 * are plain DOM, grouped by this file rather than by MapLibre, so the style
 * needs no font server either: a cluster is a button with a number in it, a
 * label is a <span>, both in the site's own type.
 *
 * Built to take hundreds of cenotes: markers that would overlap merge into a
 * numbered cluster that opens on tap, and a label that would collide with
 * another is hidden until you zoom in far enough for it to fit.
 */

type Lib = typeof import("maplibre-gl");

/* Loaded from public/vendor/ rather than bundled: see scripts/vendor.mjs. */
let lib: Promise<Lib> | null = null;
function loadLib(): Promise<Lib> {
  lib ??= (async () => {
    const url = `/vendor/maplibre-gl-${process.env.MAPLIBRE_VERSION}/maplibre-gl.mjs`;
    const [ml, pmtiles] = await Promise.all([
      import(/* webpackIgnore: true */ /* turbopackIgnore: true */ url) as Promise<Lib>,
      import("pmtiles"),
    ]);
    ml.addProtocol("pmtiles", new pmtiles.Protocol().tile);
    return ml;
  })();
  lib.catch(() => { lib = null; });
  return lib;
}

/* ------------------------------------------------------------------ style --
   The site's water column, as a map: land a shade above the abyss, the sea
   below it, the coast drawn in cenote turquoise with a soft glow, and inland
   water lit up — the lagoons and cenotes are what the map is about. Roads are
   faint foam; the Tren Maya is a dashed line in the booking slate's colour. */
const INK = {
  sea: "#03111A", land: "#0A1D26", green: "#0C2629", town: "#10262F",
  water: "#0B4450", turq: "#4FE0D2", foam: "#E6FBF6", lit: "#F7EFE3",
};

function mapStyle(url: string): ML.StyleSpecification {
  const kind = (...k: string[]): ML.ExpressionSpecification => ["in", ["get", "kind"], ["literal", k]];
  const poly: ML.ExpressionSpecification = ["==", ["geometry-type"], "Polygon"];
  const src = { source: "base" } as const;
  return {
    version: 8,
    sources: {
      base: {
        type: "vector", url,
        attribution: "© OpenStreetMap · Protomaps",
      },
      rings: { type: "geojson", data: rings() },
    },
    layers: [
      { id: "sea", type: "background", paint: { "background-color": INK.sea } },
      { id: "earth", type: "fill", ...src, "source-layer": "earth", filter: poly,
        paint: { "fill-color": INK.land } },
      { id: "cover", type: "fill", ...src, "source-layer": "landcover", filter: kind("forest", "grassland"),
        paint: { "fill-color": INK.green, "fill-opacity": 0.55 } },
      { id: "green", type: "fill", ...src, "source-layer": "landuse",
        filter: kind("forest", "wood", "nature_reserve", "national_park", "park", "wetland", "scrub", "meadow", "grassland"),
        paint: { "fill-color": INK.green, "fill-opacity": 0.45 } },
      { id: "town", type: "fill", ...src, "source-layer": "landuse",
        filter: kind("residential", "commercial", "industrial", "aerodrome"),
        paint: { "fill-color": INK.town, "fill-opacity": 0.85 } },
      { id: "reserve", type: "line", ...src, "source-layer": "landuse", filter: kind("nature_reserve", "national_park"),
        paint: { "line-color": INK.turq, "line-opacity": 0.14, "line-width": 0.8, "line-dasharray": [3, 3] } },
      { id: "sea-fill", type: "fill", ...src, "source-layer": "water", filter: ["all", poly, kind("ocean")],
        paint: { "fill-color": INK.sea } },
      { id: "inland", type: "fill", ...src, "source-layer": "water", filter: ["all", poly, ["!", kind("ocean")]],
        paint: { "fill-color": INK.water } },
      { id: "river", type: "line", ...src, "source-layer": "water", filter: ["==", ["geometry-type"], "LineString"],
        paint: { "line-color": INK.water, "line-width": 0.8 } },
      { id: "coast-glow", type: "line", ...src, "source-layer": "earth", filter: poly,
        paint: {
          "line-color": INK.turq, "line-opacity": 0.16,
          "line-width": ["interpolate", ["linear"], ["zoom"], 7, 3, 12, 14],
          "line-blur": ["interpolate", ["linear"], ["zoom"], 7, 3, 12, 12],
        } },
      { id: "coast", type: "line", ...src, "source-layer": "earth", filter: poly,
        paint: { "line-color": INK.turq, "line-opacity": 0.5,
                 "line-width": ["interpolate", ["linear"], ["zoom"], 7, 0.5, 13, 1.2] } },
      { id: "boundary", type: "line", ...src, "source-layer": "boundaries", filter: kind("region"),
        paint: { "line-color": INK.foam, "line-opacity": 0.1, "line-width": 0.8, "line-dasharray": [4, 3] } },
      { id: "road-minor", type: "line", ...src, "source-layer": "roads", minzoom: 12,
        filter: kind("minor_road"),
        paint: { "line-color": INK.foam, "line-opacity": 0.07, "line-width": 0.7 } },
      { id: "path", type: "line", ...src, "source-layer": "roads", minzoom: 12, filter: kind("path"),
        paint: { "line-color": INK.foam, "line-opacity": 0.06, "line-width": 0.6, "line-dasharray": [2, 2] } },
      { id: "road-major", type: "line", ...src, "source-layer": "roads", filter: kind("major_road"),
        paint: { "line-color": INK.foam, "line-opacity": 0.14,
                 "line-width": ["interpolate", ["linear"], ["zoom"], 7, 0.3, 13, 1.4] } },
      { id: "road-trunk", type: "line", ...src, "source-layer": "roads",
        filter: ["any", kind("highway"), ["in", ["get", "kind_detail"], ["literal", ["trunk", "primary"]]]],
        paint: { "line-color": INK.turq, "line-opacity": 0.3,
                 "line-width": ["interpolate", ["linear"], ["zoom"], 7, 0.5, 13, 2.2] } },
      { id: "rail", type: "line", ...src, "source-layer": "roads", filter: kind("rail"),
        paint: { "line-color": INK.lit, "line-opacity": 0.28, "line-width": 1, "line-dasharray": [2, 2] } },
      { id: "buildings", type: "fill", ...src, "source-layer": "buildings", minzoom: 13,
        paint: { "fill-color": INK.foam, "fill-opacity": 0.05 } },
      { id: "rings", type: "line", source: "rings",
        paint: { "line-color": INK.turq, "line-opacity": 0.2, "line-width": 1, "line-dasharray": [1, 3] } },
    ],
  };
}

/* Distance rings around Tulum — where every dive starts. */
const TULUM = TOWNS.find((t) => t.home) ?? TOWNS[0];
const RINGS_KM = [10, 25, 50, 100];

function destination(lat: number, lon: number, km: number, deg: number): [number, number] {
  const r = Math.PI / 180, d = km / 6371, b = deg * r, p1 = lat * r, l1 = lon * r;
  const p2 = Math.asin(Math.sin(p1) * Math.cos(d) + Math.cos(p1) * Math.sin(d) * Math.cos(b));
  const l2 = l1 + Math.atan2(Math.sin(b) * Math.sin(d) * Math.cos(p1), Math.cos(d) - Math.sin(p1) * Math.sin(p2));
  return [l2 / r, p2 / r];
}

function rings(): GeoJSON.FeatureCollection {
  return {
    type: "FeatureCollection",
    features: RINGS_KM.map((km) => ({
      type: "Feature", properties: { km },
      geometry: {
        type: "LineString",
        coordinates: Array.from({ length: 129 }, (_, i) => destination(TULUM.lat, TULUM.lon, km, (i * 360) / 128)),
      },
    })),
  };
}

/* ---------------------------------------------------------------- helpers */
const reduced = () => typeof matchMedia !== "undefined" && matchMedia("(prefers-reduced-motion: reduce)").matches;
const CLUSTER_PX = 52;
/* Towns that name the region at any zoom; the rest wait until there is room. */
const MAJOR_TOWNS = new Set(["Tulum", "Playa del Carmen", "Cancún", "Cozumel", "Valladolid", "Cobá", "Felipe Carrillo Puerto"]);

function el<K extends keyof HTMLElementTagNameMap>(tag: K, cls: string, text?: string) {
  const e = document.createElement(tag);
  e.className = cls;
  if (text) e.textContent = text;
  return e;
}

const overlaps = (a: DOMRect, b: DOMRect, gap = 4) =>
  a.left < b.right + gap && b.left < a.right + gap && a.top < b.bottom + gap && b.top < a.bottom + gap;

export default function CenoteMap({
  t, base, visible, focus, preview = false, tall = true,
}: {
  t: MapCopy;
  /** "/en/cenotes/" — where each cenote's page lives; null to link nowhere */
  base: string | null;
  /** Slugs to show; every cenote when left out. */
  visible?: string[];
  /** The cenote whose page this is: highlighted, and the map opens on it. */
  focus?: string;
  /** While the guide is unpublished: show coordinates, for Kay to check. */
  preview?: boolean;
  tall?: boolean;
}) {
  const wrapRef = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLDivElement>(null);
  const coordsRef = useRef<HTMLSpanElement>(null);
  const cardRef = useRef<HTMLDivElement>(null);
  const mapRef = useRef<ML.Map | null>(null);
  const libRef = useRef<Lib | null>(null);
  const markersRef = useRef<ML.Marker[]>([]);
  const visibleRef = useRef(visible);
  const selectedRef = useRef<string | null>(null);
  const [status, setStatus] = useState<"idle" | "loading" | "ready" | "error">("idle");
  const [selected, setSelected] = useState<string | null>(null);

  const shown = () => CENOTES.filter((c) => !visibleRef.current || visibleRef.current.includes(c.slug));

  /** Where the map opens: the cenote of this page, or every cenote and Tulum. */
  function home(map: ML.Map, animate: boolean) {
    const ml = libRef.current!;
    const opts = { duration: animate && !reduced() ? 900 : 0 };
    const f = focus ? CENOTES.find((c) => c.slug === focus) : undefined;
    if (f) return map.easeTo({ center: [f.lon, f.lat], zoom: 11.2, ...opts });
    const b = new ml.LngLatBounds([TULUM.lon, TULUM.lat], [TULUM.lon, TULUM.lat]);
    for (const c of shown().length ? shown() : CENOTES) b.extend([c.lon, c.lat]);
    /* Clear of the key (top left), the buttons (top right) and the credits. */
    const wide = (canvasRef.current?.clientWidth ?? 800) >= 720;
    const padding = wide ? { top: 90, right: 100, bottom: 60, left: 90 } : { top: 70, right: 64, bottom: 36, left: 28 };
    map.fitBounds(b, { padding, maxZoom: 10.6, ...opts });
  }

  /** Rebuilds every marker for the current zoom: clusters first, then labels that fit. */
  function layout() {
    const map = mapRef.current, ml = libRef.current;
    if (!map || !ml) return;
    for (const m of markersRef.current) m.remove();
    markersRef.current = [];

    const add = (node: HTMLElement, lngLat: [number, number], z = 0) => {
      const m = new ml.Marker({ element: node, anchor: "center" }).setLngLat(lngLat).addTo(map);
      m.getElement().style.zIndex = String(z);
      markersRef.current.push(m);
      return node;
    };

    /* Greedy grouping in screen space. Pixel distances depend on the zoom
       alone, not on where the map is panned, so this only reruns on zoomend. */
    const zoom = map.getZoom();
    const pts = shown().map((c) => ({ c, p: map.project([c.lon, c.lat]) }));
    /* The page's own cenote and the one whose card is open are never hidden
       in a cluster. They lead their group instead: still a point with its
       name, carrying a "+2" for the neighbours folded under it. */
    const alone = (c: Cenote) => c.slug === focus || c.slug === selectedRef.current;
    const order = pts.map((_, i) => i).sort((i, j) => Number(alone(pts[j].c)) - Number(alone(pts[i].c)));
    const taken = new Set<number>();
    const groups: { members: Cenote[]; lead?: Cenote }[] = [];
    for (const i of order) {
      if (taken.has(i)) continue;
      taken.add(i);
      const a = pts[i];
      const g = { members: [a.c], lead: alone(a.c) ? a.c : undefined };
      if (zoom < 14) {
        for (const j of order) {
          const b = pts[j];
          if (!taken.has(j) && !alone(b.c) && Math.hypot(a.p.x - b.p.x, a.p.y - b.p.y) < CLUSTER_PX) {
            taken.add(j);
            g.members.push(b.c);
          }
        }
      }
      groups.push(g);
    }
    /* Greedy grouping can leave two clusters touching; merge until none do. */
    const centre = (g: Cenote[]) => {
      const pp = g.map((c) => map.project([c.lon, c.lat]));
      return { x: pp.reduce((s, q) => s + q.x, 0) / pp.length, y: pp.reduce((s, q) => s + q.y, 0) / pp.length };
    };
    for (let merged = zoom < 14; merged;) {
      merged = false;
      outer: for (let i = 0; i < groups.length; i++) {
        for (let j = i + 1; j < groups.length; j++) {
          if (groups[i].lead || groups[j].lead) continue;
          const a = centre(groups[i].members), b = centre(groups[j].members);
          if (Math.hypot(a.x - b.x, a.y - b.y) < CLUSTER_PX) {
            groups[i].members = groups[i].members.concat(groups[j].members);
            groups.splice(j, 1);
            merged = true;
            break outer;
          }
        }
      }
    }
    /** Opens a group: zooms until its cenotes fill the map. */
    const open = (g: Cenote[]) => {
      const b = new ml.LngLatBounds([g[0].lon, g[0].lat], [g[0].lon, g[0].lat]);
      for (const c of g) b.extend([c.lon, c.lat]);
      map.fitBounds(b, { padding: 90, maxZoom: Math.max(zoom + 2, 14.5), duration: reduced() ? 0 : 800 });
    };

    const labels: { node: HTMLElement; label: HTMLElement; rank: number }[] = [];
    const obstacles: HTMLElement[] = [];

    /* Towns and the distance rings sit underneath, and give way to cenotes. */
    for (const town of TOWNS) {
      if (!town.home && !MAJOR_TOWNS.has(town.name) && zoom < 9.5) continue;
      const node = el("div", `ct${town.home ? " ct--home" : ""}`);
      node.setAttribute("aria-hidden", "true");
      const label = el("span", "ct__label", town.name);
      node.append(el("i", "ct__dot"), label);
      add(node, [town.lon, town.lat], town.home ? 2 : 1);
      obstacles.push(node.firstElementChild as HTMLElement);
      labels.push({ node, label, rank: town.home ? 50 : 10 });
    }
    for (const km of RINGS_KM) {
      const node = el("div", "cr");
      node.setAttribute("aria-hidden", "true");
      const label = el("span", "cr__label", `${km} km`);
      node.append(label);
      add(node, destination(TULUM.lat, TULUM.lon, km, 315), 1);
      labels.push({ node, label, rank: 0 });
    }

    for (const { members, lead } of groups) {
      if (!lead && members.length > 1) {
        const lon = members.reduce((s, c) => s + c.lon, 0) / members.length;
        const lat = members.reduce((s, c) => s + c.lat, 0) / members.length;
        const node = el("button", "cc");
        node.type = "button";
        const label = t.cluster.replace("{n}", String(members.length));
        node.setAttribute("aria-label", label);
        node.title = label;
        node.style.setProperty("--n", String(Math.min(members.length, 30)));
        node.append(el("span", "cc__n", String(members.length)));
        node.addEventListener("click", () => open(members));
        add(node, [lon, lat], 5);
        obstacles.push(node.firstElementChild as HTMLElement);
        continue;
      }
      const c = lead ?? members[0];
      const on = c.slug === selectedRef.current;
      const node = el("button", `cm cm--${c.type}${c.approx ? " cm--approx" : ""}${c.slug === focus ? " is-focus" : ""}${on ? " is-on" : ""}`);
      node.type = "button";
      node.dataset.slug = c.slug;
      node.setAttribute("aria-label", c.name);
      node.setAttribute("aria-pressed", String(on));
      const dot = el("i", "cm__dot");
      const label = el("span", "cm__label", c.short);
      node.append(dot, label);
      node.addEventListener("click", () => select(c.slug));
      add(node, [c.lon, c.lat], on ? 20 : c.slug === focus ? 15 : 10);
      obstacles.push(dot);
      labels.push({ node, label, rank: on ? 1000 : c.slug === focus ? 900 : c.type === "deep" ? 120 : 100 });

      if (members.length > 1) {
        const more = el("button", "cmore");
        more.type = "button";
        const text = t.cluster.replace("{n}", String(members.length - 1));
        more.setAttribute("aria-label", text);
        more.title = text;
        more.append(el("span", "cmore__n", `+${members.length - 1}`));
        more.addEventListener("click", () => open(members));
        const m = new ml.Marker({ element: more, anchor: "center", offset: [15, -15] }).setLngLat([c.lon, c.lat]).addTo(map);
        m.getElement().style.zIndex = "21";
        markersRef.current.push(m);
        obstacles.push(more.firstElementChild as HTMLElement);
      }
    }

    /* Labels: right of the point, else left, else hidden until there is room. */
    const placed: DOMRect[] = [];
    const walls = obstacles.map((o) => ({ o, r: o.getBoundingClientRect() }));
    for (const l of labels.sort((a, b) => b.rank - a.rank)) {
      const own = l.node.querySelector("i");
      let ok = false;
      for (const left of [false, true]) {
        l.node.classList.toggle("is-left", left);
        const r = l.label.getBoundingClientRect();
        if (!placed.some((p) => overlaps(p, r)) && !walls.some((w) => w.o !== own && overlaps(w.r, r, 2))) {
          placed.push(r);
          ok = true;
          break;
        }
      }
      /* The chosen cenote and the page's own always keep their name. */
      if (!ok && l.rank >= 900) {
        l.node.classList.remove("is-left");
        placed.push(l.label.getBoundingClientRect());
        ok = true;
      }
      l.node.classList.toggle("is-quiet", !ok);
    }
  }

  function select(slug: string | null) {
    selectedRef.current = slug;
    setSelected(slug);
    for (const m of markersRef.current) {
      const node = m.getElement();
      if (!node.dataset.slug) continue;
      const on = node.dataset.slug === slug;
      node.classList.toggle("is-on", on);
      node.setAttribute("aria-pressed", String(on));
      node.style.zIndex = on ? "20" : node.classList.contains("is-focus") ? "15" : "10";
      if (on) node.classList.remove("is-quiet");
    }
    const map = mapRef.current;
    const c = slug ? CENOTES.find((x) => x.slug === slug) : undefined;
    if (!map || !c) return;
    /* Keep the point clear of the card: beside it on a wide map, above it on a phone. */
    const wide = (canvasRef.current?.clientWidth ?? 0) >= 720;
    const offset: [number, number] = wide ? [170, 0] : [0, -110];
    map.easeTo({ center: [c.lon, c.lat], offset, duration: reduced() ? 0 : 700 });
  }

  /* Load when the map is about to scroll into view — not before. */
  useEffect(() => {
    const wrap = wrapRef.current;
    if (!wrap) return;
    let cancelled = false;
    const start = () => {
      setStatus("loading");
      loadLib().then((ml) => {
        if (cancelled || !canvasRef.current) return;
        libRef.current = ml;
        const map = new ml.Map({
          container: canvasRef.current,
          style: mapStyle(`pmtiles://${location.origin}${BASEMAP}`),
          center: [TULUM.lon, TULUM.lat], zoom: 10,
          minZoom: 7, maxZoom: 16,
          maxBounds: [[MAP_BOUNDS[0], MAP_BOUNDS[1]], [MAP_BOUNDS[2], MAP_BOUNDS[3]]],
          attributionControl: false,
          cooperativeGestures: true,
          dragRotate: false, pitchWithRotate: false, touchPitch: false,
          locale: {
            "CooperativeGesturesHandler.WindowsHelpText": t.gestureWin,
            "CooperativeGesturesHandler.MacHelpText": t.gestureMac,
            "CooperativeGesturesHandler.MobileHelpText": t.gestureTouch,
          },
        });
        map.touchZoomRotate.disableRotation();
        map.keyboard.disableRotation();
        mapRef.current = map;
        home(map, false);
        /* The centre's position, bottom left, like the strip under the hero. */
        const readout = () => {
          const c = map.getCenter();
          if (coordsRef.current) {
            coordsRef.current.textContent =
              `${Math.abs(c.lat).toFixed(3)}°${c.lat >= 0 ? "N" : "S"} ${Math.abs(c.lng).toFixed(3)}°${c.lng >= 0 ? "E" : "W"}`;
          }
        };
        map.on("load", () => {
          if (cancelled) return;
          setStatus("ready");
          layout();
          requestAnimationFrame(readout);
        });
        map.on("zoomend", layout);
        map.on("move", readout);
      }).catch(() => { if (!cancelled) setStatus("error"); });
    };
    const io = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting)) { io.disconnect(); start(); }
    }, { rootMargin: "400px 0px" });
    io.observe(wrap);
    return () => {
      cancelled = true;
      io.disconnect();
      mapRef.current?.remove();
      mapRef.current = null;
      markersRef.current = [];
    };
    // Built once per mount; the filter and the selection are pushed in below.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /* A filter change regroups the markers; a hidden cenote cannot stay open. */
  useEffect(() => {
    visibleRef.current = visible;
    if (selectedRef.current && visible && !visible.includes(selectedRef.current)) select(null);
    layout();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible?.join(",")]);

  useEffect(() => {
    if (!selected) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && select(null);
    addEventListener("keydown", onKey);
    return () => removeEventListener("keydown", onKey);
  }, [selected]);

  const c = selected ? CENOTES.find((x) => x.slug === selected) : undefined;
  const away = c ? fromTulum(c) : null;
  const zoomBy = (d: number) => mapRef.current?.zoomTo(mapRef.current.getZoom() + d, { duration: reduced() ? 0 : 300 });

  return (
    <div ref={wrapRef} className={`cmap${tall ? "" : " cmap--short"}`} data-status={status}>
      <div ref={canvasRef} className="cmap__canvas" role="region" aria-label={t.mapLabel} />

      {status !== "ready" && (
        <p className="cmap__wait data" role={status === "error" ? "alert" : undefined}>
          {status === "error" ? t.mapError : t.mapLoading}
        </p>
      )}

      {status === "ready" && (
        <>
          <div className="cmap__ctl" role="group">
            <button type="button" onClick={() => zoomBy(1)} aria-label={t.zoomIn} title={t.zoomIn}>+</button>
            <button type="button" onClick={() => zoomBy(-1)} aria-label={t.zoomOut} title={t.zoomOut}>−</button>
            <button type="button" onClick={() => mapRef.current && home(mapRef.current, true)}
                    aria-label={t.reset} title={t.reset}>
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <circle cx="8" cy="8" r="5.2" stroke="currentColor" strokeWidth="1.3" />
                <circle cx="8" cy="8" r="1.6" fill="currentColor" />
                <path d="M8 0.8v2.4M8 12.8v2.4M0.8 8h2.4M12.8 8h2.4" stroke="currentColor" strokeWidth="1.3" />
              </svg>
            </button>
          </div>

          <ul className="cmap__key data" aria-hidden="true">
            <li><i className="cm__dot cm__dot--open" />{t.types.open}</li>
            <li><i className="cm__dot cm__dot--cavern" />{t.types.cavern}</li>
            <li><i className="cm__dot cm__dot--deep" />{t.types.deep}</li>
          </ul>

          <p className="cmap__foot data">
            <span ref={coordsRef} className="cmap__coords" />
            <span>© <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a> · <a href="https://protomaps.com" rel="noopener">Protomaps</a></span>
          </p>
        </>
      )}

      {c && away && (
        <div ref={cardRef} className="cmap__card" role="dialog" aria-label={c.name}>
          <button type="button" className="cmap__x" onClick={() => select(null)} aria-label={t.close}>
            <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
              <path d="M1 1l10 10M11 1L1 11" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
            </svg>
          </button>
          <p className="tag tag--plain">{t.types[c.type]}</p>
          <h3 className="cmap__name">{c.name}</h3>
          {c.aka && <p className="data">{c.aka}</p>}
          <dl className="spec">
            <div><dt>{t.diveDepth}</dt><dd>{diveDepth(c, t.upTo)}</dd></div>
            {c.depthM !== null && c.depthM > c.diveMaxM && (
              <div><dt>{t.cenoteDepth}</dt><dd>{c.depthM} m</dd></div>
            )}
            <div><dt>{t.levelTag}</dt><dd>{t.levels[c.level]}</dd></div>
            <div><dt>{t.fromTulum}</dt><dd>{away.km} km {t.compass[away.dir]}</dd></div>
          </dl>
          {c.snorkel && <p className="cmap__note">{t.snorkelToo}</p>}
          {(c.approx || preview) && (
            <p className="cmap__note cmap__note--warn">
              {c.approx && <>{t.approx}<br /></>}
              {preview && (
                <a href={`https://www.google.com/maps?q=${c.lat},${c.lon}`} rel="noopener" target="_blank">
                  {c.lat.toFixed(5)}, {c.lon.toFixed(5)} ↗
                </a>
              )}
            </p>
          )}
          {base && c.slug !== focus && (
            <a className="btn btn--ghost btn--sm" href={`${base}${c.slug}/`}>{t.see}</a>
          )}
        </div>
      )}
    </div>
  );
}
