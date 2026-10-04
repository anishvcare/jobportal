import type { Metadata } from "next";
import { StaticPage } from "@/components/layout/StaticPage";

export const metadata: Metadata = {
  title: "Contact",
  description:
    "How to reach NexusFlow Services LLP for overseas recruitment and MBBS admission support.",
};

export default function ContactPage() {
  return (
    <StaticPage title="Contact us">
      <p>We&apos;re happy to help candidates, students and employers.</p>
      <h2>Registered office</h2>
      <p>
        NexusFlow Services LLP
        <br />
        Door No 13/227, Kakkoor, Piravom
        <br />
        Muvattupuzha, Ernakulam
        <br />
        Kerala, India &ndash; 686662
      </p>
      <h2>Email</h2>
      <p>
        For support and general enquiries:{" "}
        <a href="mailto:anishvcare@gmail.com">anishvcare@gmail.com</a>
      </p>
      <p>Office hours: Monday to Saturday, 9:30 am &ndash; 5:30 pm IST.</p>
    </StaticPage>
  );
}
