import { BOOKABLE, ADDONS, PICKUPS } from "@/content/dives";

export interface BookingInput {
  dive: string; date: string; cert: string; divers: number;
  pickup: string; addons: string[]; name: string; email: string; locale: string;
}

export interface Quote {
  dive: (typeof BOOKABLE)[number];
  divers: number;
  totalUsd: number;
  depositUsd: number;
  lines: { label: string; amount: number }[];
}

/** Share of the total taken up front. The rest is settled on the day. */
export const DEPOSIT_RATE = Number(process.env.DEPOSIT_RATE ?? 0.3);

/**
 * Prices are recomputed here from slugs alone. The browser sends choices,
 * never amounts — a tampered payload cannot lower what is charged.
 */
export function quote(input: BookingInput): Quote | null {
  const dive = BOOKABLE.find((d) => d.slug === input.dive);
  const pick = PICKUPS.find((p) => p.slug === input.pickup);
  if (!dive || !pick) return null;

  const divers = Math.min(8, Math.max(1, Math.trunc(input.divers)));
  const chosen = ADDONS.filter((a) => input.addons?.includes(a.slug));

  const lines = [
    { label: `${dive.slug} × ${divers}`, amount: dive.price * divers },
    ...(pick.price ? [{ label: `pickup ${pick.slug}`, amount: pick.price }] : []),
    ...chosen.map((a) => ({
      label: a.slug + (a.perDiver ? ` × ${divers}` : ""),
      amount: a.price * (a.perDiver ? divers : 1),
    })),
  ];

  const totalUsd = lines.reduce((s, l) => s + l.amount, 0);
  return { dive, divers, totalUsd, depositUsd: Math.round(totalUsd * DEPOSIT_RATE), lines };
}

export function validate(input: BookingInput): string | null {
  if (!input?.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.email)) return "email";
  if (!input?.name || input.name.trim().length < 2) return "name";
  if (!input?.date || Number.isNaN(Date.parse(input.date))) return "date";
  if (new Date(input.date) < new Date(new Date().toDateString())) return "date-past";
  return null;
}
