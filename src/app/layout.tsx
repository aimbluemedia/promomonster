import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  metadataBase: new URL("https://promomonster.com"),
  title: {
    default: "PromoMonster — Real People. Real Answers.",
    template: "%s · PromoMonster",
  },
  description:
    "Find out what real people think of your website, your content and your ad creative. Studies from 50 to 500 real respondents, usually back the same day.",
  openGraph: {
    title: "PromoMonster — Real People. Real Answers.",
    description:
      "Find out what real people think of your website, your content and your ad creative.",
    type: "website",
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body className="font-sans">
        <a
          href="#main"
          className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-white"
        >
          Skip to content
        </a>
        {children}
      </body>
    </html>
  );
}
