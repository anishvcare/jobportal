import type { Metadata } from "next";
import { StaticPage } from "@/components/layout/StaticPage";

export const metadata: Metadata = { title: "Contact" };

export default function ContactPage() {
  return (
    <StaticPage title="Contact us">
      <p>We&apos;re happy to help candidates and employers.</p>
      <h2>Office</h2>
      <p>
        Nexus Flow
        <br />
        [Street address], Kottayam, Kerala, India
      </p>
      <h2>Phone and email</h2>
      <p>
        Phone / WhatsApp: [+91 XXXXX XXXXX]
        <br />
        Email: [support@example.com]
      </p>
      <p>Office hours: Monday to Saturday, 9:30 am – 5:30 pm IST.</p>
    </StaticPage>
  );
}
