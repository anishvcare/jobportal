import type { Metadata } from "next";
import { StaticPage } from "@/components/layout/StaticPage";

export const metadata: Metadata = {
  title: "About us",
  description:
    "NexusFlow Services LLP is an overseas recruitment and MBBS admission portal based in Ernakulam, Kerala.",
};

export default function AboutPage() {
  return (
    <StaticPage title="About NexusFlow Services LLP">
      <p>
        NexusFlow Services LLP is an overseas recruitment and MBBS admission
        portal based in Kakkoor, Ernakulam, Kerala. We help candidates and
        students in India connect with employers and universities abroad &mdash;
        including in Russia, Israel, Vietnam and Uzbekistan.
      </p>
      <h2>What we do</h2>
      <ul>
        <li>
          <strong>Overseas recruitment.</strong> Candidates build one profile,
          upload their documents once and apply to jobs with employers abroad.
        </li>
        <li>
          <strong>MBBS admissions.</strong> We guide students through overseas MBBS
          admission applications and documentation.
        </li>
        <li>
          Approved employers post jobs and manage applicants in one place, and our
          team helps match verified employers and universities with suitable
          candidates.
        </li>
      </ul>
      <h2>Contact</h2>
      <p>
        NexusFlow Services LLP
        <br />
        Door No 13/227, Kakkoor, Piravom, Muvattupuzha, Ernakulam, Kerala, India
        &ndash; 686662
        <br />
        <a href="mailto:anishvcare@gmail.com">anishvcare@gmail.com</a>
      </p>
    </StaticPage>
  );
}
