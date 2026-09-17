import { NextResponse, type NextRequest } from "next/server";
import { LOCALES, DEFAULT_LOCALE } from "@/lib/i18n";

/**
 * The default locale lives at the root, the others behind a prefix. Anything
 * that is not already prefixed is rewritten onto the default so /es and /fr
 * stay real URLs while / stays clean for the English pages Google ranks.
 */
export function proxy(req: NextRequest) {
  const { pathname } = req.nextUrl;
  const prefixed = LOCALES.some((l) => pathname === `/${l}` || pathname.startsWith(`/${l}/`));
  if (prefixed) return NextResponse.next();

  const url = req.nextUrl.clone();
  url.pathname = `/${DEFAULT_LOCALE}${pathname}`;
  return NextResponse.rewrite(url);
}

export const config = {
  matcher: ["/((?!api|_next|assets|favicon.ico|robots.txt|sitemap.xml).*)"],
};
