import { PRODUCTS } from "../content/products.ts";

export interface BookingInput {
  product: string; option: number; date: string; cert: string;
  divers: number; name: string; email: string; locale: string;
}

export interface Quote {
  product: (typeof PRODUCTS)[number];
  option: { dives: number; price: number };
  divers: number;
  totalUsd: number;
  depositUsd: number;
}

/** Share of the total taken up front. The rest is settled on the day. */
export const DEPOSIT_RATE = Number(process.env.DEPOSIT_RATE ?? 0.3);

/**
 * Prices are recomputed here from slugs alone. The browser sends choices,
 * never amounts — a tampered payload cannot lower what is charged.
 *
 * Transport, equipment, cenote entrance and snacks are included in every
 * price on the menu, so there is nothing to add on top.
 */
export function quote(input: BookingInput): Quote | null {
  const product = PRODUCTS.find((p) => p.slug === input.product);
  if (!product) return null;

  const option = product.options.find((o) => o.dives === Number(input.option));
  if (!option) return null;

  const divers = Math.min(8, Math.max(1, Math.trunc(input.divers)));
  const totalUsd = option.price * divers;

  return { product, option, divers, totalUsd, depositUsd: Math.round(totalUsd * DEPOSIT_RATE) };
}

export function validate(input: BookingInput): string | null {
  if (!input?.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.email)) return "email";
  if (!input?.name || input.name.trim().length < 2) return "name";
  if (!input?.date || Number.isNaN(Date.parse(input.date))) return "date";
  if (new Date(input.date) < new Date(new Date().toDateString())) return "date-past";
  return null;
}
