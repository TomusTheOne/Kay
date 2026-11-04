import { notFound } from "next/navigation";
import { isLocale, getDictionary } from "@/lib/i18n";
import Outcome from "@/components/Outcome";

export const metadata = { robots: { index: false, follow: false } };

export default async function Page({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();
  const t = (await getDictionary(locale)).booking.failed;
  return <Outcome locale={locale} tag={t.tag} h2={t.h2} p={t.p} cta={t.cta} tone="bad" />;
}
