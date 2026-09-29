import type { Metadata } from "next";
import { StaticPage } from "@/components/layout/StaticPage";

export const metadata: Metadata = { title: "About us" };

export default function AboutPage() {
  return (
    <StaticPage title="About Nexus Flow">
      <p>
        Nexus Flow is a job portal run from Kottayam, Kerala. We connect job seekers in India with employers in
        Kerala, across India, in Russia and in other countries.
      </p>
      <h2>What we do</h2>
      <ul>
        <li>Candidates build one profile, upload their documents once and apply to many jobs.</li>
        <li>Approved employers post jobs and manage applicants.</li>
        <li>Our team helps verified employers find suitable candidates.</li>
      </ul>
      <p>[Placeholder text: replace with your company story, registration details and team.]</p>
    </StaticPage>
  );
}
