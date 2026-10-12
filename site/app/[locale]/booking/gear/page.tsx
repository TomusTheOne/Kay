import { notFound } from "next/navigation";
import { isLocale, getDictionary } from "@/lib/i18n";
import GearForm from "@/components/GearForm";
import { PRIVATE_PAGE } from "@/lib/seo";

/* Reached only from the link in a confirmation email: never indexed. */
export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) return {};
  const t = await getDictionary(locale);
  return { ...PRIVATE_PAGE, title: t.gear.metaTitle };
}

export default async function Page({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();
  const t = await getDictionary(locale);
  const names = Object.fromEntries(Object.entries(t.products.items).map(([slug, p]) => [slug, p.name]));
  return <GearForm locale={locale} t={t.gear} productName={names} />;
}
