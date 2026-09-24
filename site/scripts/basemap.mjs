/**
 * Cuts the cenote map's background out of Protomaps' daily planet build and
 * writes it as one PMTiles file the site serves itself — no tile service, no
 * API key, no third party on the page.
 *
 *   node scripts/basemap.mjs            # the latest daily build
 *   node scripts/basemap.mjs 20260923   # a given one
 *
 * The planet is read with HTTP range requests, tile by tile, for the box
 * below only. go-pmtiles' `extract` does the same thing, but its binary lives
 * on GitHub Releases, which is not always reachable; the format is small
 * enough to write here (https://github.com/protomaps/PMTiles/blob/main/spec/v3/spec.md).
 *
 * Output: public/tiles/tulum-<date>.pmtiles. The date is in the name so a
 * new cut never has to overwrite the old one in a visitor's cache, and so
 * the deploy can compare files by name and size alone.
 */
import { PMTiles, zxyToTileId } from "pmtiles";
import { gzipSync, gunzipSync } from "node:zlib";
import { createHash } from "node:crypto";
import { mkdir, readFile, writeFile, readdir, rm } from "node:fs/promises";

/* About 120 km around Tulum each way: Cancún's south edge to Sian Ka'an,
   Cozumel to Valladolid. Zoom 13 is enough to place a cenote on its road;
   the map overzooms past it without asking for anything more. */
const BOX = { west: -88.7, south: 19.1, east: -86.3, north: 21.3 };
const MAX_ZOOM = 13;
const CENTER = { lon: -87.46, lat: 20.21, zoom: 9 };
const OUT_DIR = "public/tiles";

const lonToX = (lon, z) => Math.floor(((lon + 180) / 360) * 2 ** z);
const latToY = (lat, z) => {
  const r = (lat * Math.PI) / 180;
  return Math.floor(((1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2) * 2 ** z);
};

async function latestBuild() {
  for (let back = 0; back < 20; back++) {
    const d = new Date(Date.now() - back * 864e5).toISOString().slice(0, 10).replaceAll("-", "");
    const res = await fetch(`https://build.protomaps.com/${d}.pmtiles`, { headers: { Range: "bytes=0-126" } });
    if (res.status === 206) return d;
  }
  throw new Error("no Protomaps daily build found in the last 20 days");
}

const date = process.argv[2] ?? (await latestBuild());
const source = new PMTiles(`https://build.protomaps.com/${date}.pmtiles`);
const header = await source.getHeader();
const sourceMeta = await source.getMetadata();
console.log(`Protomaps build ${date}: zoom ${header.minZoom}–${header.maxZoom}`);

/* ------------------------------------------------------------ fetch tiles */

const wanted = [];
for (let z = 0; z <= MAX_ZOOM; z++) {
  const x0 = lonToX(BOX.west, z), x1 = lonToX(BOX.east, z);
  const y0 = latToY(BOX.north, z), y1 = latToY(BOX.south, z);
  for (let x = x0; x <= x1; x++) for (let y = y0; y <= y1; y++) wanted.push([z, x, y]);
}
console.log(`${wanted.length} tiles to read`);

const tiles = new Map();   // tileId -> gzipped bytes
let done = 0;
async function worker(queue) {
  for (let job = queue.pop(); job; job = queue.pop()) {
    const [z, x, y] = job;
    for (let attempt = 1; ; attempt++) {
      try {
        const t = await source.getZxy(z, x, y);
        // The reader hands tiles back decompressed; they are stored gzipped,
        // as in the source. mtime 0 keeps the output byte-identical run to run.
        if (t && t.data.byteLength > 0) tiles.set(zxyToTileId(z, x, y), gzipSync(Buffer.from(t.data), { level: 9 }));
        break;
      } catch (e) {
        if (attempt >= 4) throw e;
        await new Promise((r) => setTimeout(r, 500 * attempt));
      }
    }
    if (++done % 500 === 0) console.log(`  ${done}/${wanted.length}`);
  }
}
const queue = [...wanted].reverse();
await Promise.all(Array.from({ length: 16 }, () => worker(queue)));

/* ------------------------------------------------------ lay out the data */

// Identical tiles — open sea, mostly — are stored once.
const ids = [...tiles.keys()].sort((a, b) => a - b);
const byHash = new Map();
const chunks = [];
let dataLength = 0;
const entries = [];
for (const id of ids) {
  const bytes = tiles.get(id);
  const hash = createHash("sha1").update(bytes).digest("hex");
  let place = byHash.get(hash);
  if (!place) {
    place = { offset: dataLength, length: bytes.length };
    byHash.set(hash, place);
    chunks.push(bytes);
    dataLength += bytes.length;
  }
  const last = entries[entries.length - 1];
  if (last && last.offset === place.offset && last.tileId + last.runLength === id) {
    last.runLength++;
  } else {
    entries.push({ tileId: id, offset: place.offset, length: place.length, runLength: 1 });
  }
}

function varint(n, out) {
  while (n >= 0x80) {
    out.push((n % 0x80) | 0x80);
    n = Math.floor(n / 0x80);
  }
  out.push(n);
}

function serializeDirectory(list) {
  const out = [];
  varint(list.length, out);
  let lastId = 0;
  for (const e of list) { varint(e.tileId - lastId, out); lastId = e.tileId; }
  for (const e of list) varint(e.runLength, out);
  for (const e of list) varint(e.length, out);
  list.forEach((e, i) => {
    const prev = list[i - 1];
    varint(i > 0 && e.offset === prev.offset + prev.length ? 0 : e.offset + 1, out);
  });
  return gzipSync(Buffer.from(out), { level: 9 });
}

// The root directory must fit in the first 16 KiB with the header; if it
// does not, entries go into leaf directories that the root points to.
let root = serializeDirectory(entries);
let leaves = Buffer.alloc(0);
if (127 + root.length > 16384) {
  const leafDirs = [];
  const rootEntries = [];
  let offset = 0;
  for (let size = 4096; ; size = Math.floor(size / 2)) {
    leafDirs.length = 0; rootEntries.length = 0; offset = 0;
    for (let i = 0; i < entries.length; i += size) {
      const leaf = serializeDirectory(entries.slice(i, i + size));
      rootEntries.push({ tileId: entries[i].tileId, offset, length: leaf.length, runLength: 0 });
      leafDirs.push(leaf);
      offset += leaf.length;
    }
    root = serializeDirectory(rootEntries);
    if (127 + root.length <= 16384) break;
  }
  leaves = Buffer.concat(leafDirs);
}

const metadata = gzipSync(Buffer.from(JSON.stringify({
  name: "Kay Diving — Tulum",
  attribution: sourceMeta?.attribution ?? '<a href="https://openstreetmap.org/copyright">© OpenStreetMap</a>',
  vector_layers: sourceMeta?.vector_layers ?? [],
  "planetiler:osm:osmosisreplicationtime": sourceMeta?.["planetiler:osm:osmosisreplicationtime"],
  source: `https://build.protomaps.com/${date}.pmtiles`,
})), { level: 9 });

/* ---------------------------------------------------------------- header */

const h = Buffer.alloc(127);
h.write("PMTiles", 0, "ascii");
h.writeUInt8(3, 7);
const rootOffset = 127;
const metaOffset = rootOffset + root.length;
const leafOffset = metaOffset + metadata.length;
const dataOffset = leafOffset + leaves.length;
const u64 = (v, at) => h.writeBigUInt64LE(BigInt(v), at);
u64(rootOffset, 8);   u64(root.length, 16);
u64(metaOffset, 24);  u64(metadata.length, 32);
u64(leafOffset, 40);  u64(leaves.length, 48);
u64(dataOffset, 56);  u64(dataLength, 64);
u64(ids.length, 72);             // addressed tiles
u64(entries.length, 80);         // tile entries
u64(chunks.length, 88);          // distinct tile contents
h.writeUInt8(1, 96);             // clustered
h.writeUInt8(2, 97);             // internal compression: gzip
h.writeUInt8(2, 98);             // tile compression: gzip
h.writeUInt8(1, 99);             // tile type: MVT
h.writeUInt8(0, 100);
h.writeUInt8(MAX_ZOOM, 101);
const e7 = (v, at) => h.writeInt32LE(Math.round(v * 1e7), at);
e7(BOX.west, 102); e7(BOX.south, 106); e7(BOX.east, 110); e7(BOX.north, 114);
h.writeUInt8(CENTER.zoom, 118);
e7(CENTER.lon, 119); e7(CENTER.lat, 123);

await mkdir(OUT_DIR, { recursive: true });
for (const old of await readdir(OUT_DIR)) {
  if (old.startsWith("tulum-") && old.endsWith(".pmtiles")) await rm(`${OUT_DIR}/${old}`);
}
const file = `${OUT_DIR}/tulum-${date}.pmtiles`;
await writeFile(file, Buffer.concat([h, root, metadata, leaves, ...chunks]));

// Read it back the way the browser will, so a malformed archive fails here.
const check = new PMTiles(new (class {
  constructor(buf) { this.buf = buf; }
  getKey() { return file; }
  async getBytes(offset, length) { return { data: this.buf.buffer.slice(this.buf.byteOffset + offset, this.buf.byteOffset + offset + length) }; }
})(Buffer.concat([h, root, metadata, leaves, ...chunks])));
const probe = await check.getZxy(12, lonToX(CENTER.lon, 12), latToY(CENTER.lat, 12));
if (!probe || probe.data.byteLength === 0) throw new Error("the written archive does not return the Tulum tile");
gunzipSync(tiles.get(zxyToTileId(12, lonToX(CENTER.lon, 12), latToY(CENTER.lat, 12))));

const mb = (n) => (n / 1048576).toFixed(1) + " MB";
console.log(`${file}: ${mb(dataOffset + dataLength)} — ${ids.length} tiles, ${chunks.length} distinct, ${entries.length} entries${leaves.length ? `, ${leaves.length} bytes of leaf directories` : ""}`);

/* The map reads the file name from content/cenotes.json, so point it at the
   new cut — otherwise the site keeps asking for the one just deleted. */
const cenotesPath = "content/cenotes.json";
const cenotes = await readFile(cenotesPath, "utf8");
await writeFile(cenotesPath, cenotes.replace(/"basemap": "[^"]*"/, `"basemap": "/tiles/tulum-${date}.pmtiles"`));
console.log(`${cenotesPath} now points at /tiles/tulum-${date}.pmtiles`);
