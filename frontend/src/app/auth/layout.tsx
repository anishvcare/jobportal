import type { Metadata } from "next";

export const metadata: Metadata = { robots: { index: false, follow: false } };

export default function AuthLayout({ children }: LayoutProps<"/auth">) {
  return <div className="mx-auto flex w-full max-w-md flex-col px-4 py-10">{children}</div>;
}
