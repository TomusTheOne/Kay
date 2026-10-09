import data from "./places.json";
import { CENOTES as DIVE_SITES } from "./cenotes";

/**
 * Cenotes you can visit on your own, and the Maya sites within reach.
 *
 * Deliberately not the same list as content/cenotes.json. That one is where
 * Kay dives, with depths, coordinates and the courses that go there; this one
 * is what a visitor might drive to on a day off, including places nobody
 * dives. Four appear in both, which is the point rather than a mistake: a
 * diver reading about Dos Ojos here should be able to click through to the
 * page that says what diving it involves.
 *
 * Separate from products.json too: that file is what Kay sells and is read by
 * the PHP that takes money. Mixing them would put tourist copy on the path of
 * a payment.
 */

export type CenoteType = "open" | "semi-open" | "cavern" | "channel";
export type Activity = "swim" | "snorkel" | "dive";

export interface Cenote {
  slug: string;
  type: CenoteType;
  /** What the guide states is allowed there — not what we wish were allowed. */
  activities: Activity[];
  photo: string | null;
}

export type Crowd = "low" | "medium" | "medium-high" | "high" | "very-high";
export type Walking = "easy" | "easy-moderate" | "moderate" | "moderate-high";
export type Transit = "easy" | "possible" | "complex" | "ferry";

export interface Ruin {
  slug: string;
  /** [min, max] km from Tulum. Both null where the trip is not a drive. */
  km: [number | null, number | null];
  minutes: [number, number];
  hours: string;
  lastEntry: string;
  stayHours: [number, number];
  crowd: Crowd;
  walking: Walking;
  transit: Transit;
  /** The INAH ticket alone, in pesos. */
  inah: { national: number; foreign: number };
  /** A second, separately collected charge — CULTUR at Ek' Balam and Chichén. */
  extra: { key: string; national: number; foreign: number; parking: number | null } | null;
  /** The guide says other charges may apply here but gives no figure. */
  extraPossible: boolean;
  booking: boolean;
  photo: string | null;
}

/**
 * Which of these Kay actually dives, answered by the dive guide rather than
 * by a boolean someone has to remember to update. A slug that exists in both
 * files is a cenote with its own page, so the card can link to it; one that
 * does not is a place to visit, nothing more. Keeping it derived means the
 * two lists cannot drift into disagreeing about the same cenote.
 */
const DIVED = new Set(DIVE_SITES.map((c) => c.slug));

export const CENOTES = (data.cenotes as Cenote[]).map((c) => ({
  ...c,
  dived: DIVED.has(c.slug),
}));
export const RUINS = data.ruins as Ruin[];

/**
 * When the entrance fees were last checked, and where. Shown on the page.
 *
 * Fees and opening hours move, and the guide these came from says so on every
 * page: INAH changes them, and at several sites the ticket is not the whole
 * bill. A figure with no date beside it is how a visitor arrives at Chichén
 * Itzá holding 105 pesos when the gate wants 295 — and blames the website that
 * told them. So the date ships with the number, and the link goes to INAH.
 */
export const INAH_VERIFIED = data.inahVerified as string;
export const INAH_SOURCE = data.inahSource as string;
