import type { Metadata } from "next";
import { StaticPage } from "@/components/layout/StaticPage";

export const metadata: Metadata = { title: "Privacy Policy" };

export default function PrivacyPage() {
  return (
    <StaticPage title="Privacy Policy" updated="[date]">
      <p>[Placeholder text: have this policy reviewed by a legal professional before launch.]</p>
      <h2>What we collect</h2>
      <ul>
        <li>Your Google account name, email address and profile picture when you sign in.</li>
        <li>Profile details you enter, such as date of birth, address, education and work experience.</li>
        <li>Documents you upload, such as your photo, Aadhaar card, SSLC book, certificates and passport pages.</li>
      </ul>
      <h2>Who can see your documents</h2>
      <p>
        Your documents are stored privately. Only you, the Nexus Flow admin team and employers we have specifically
        approved to search candidates can view or download them. Employers you apply to can see your profile and
        resume. Every document download is logged.
      </p>
      <h2>Your choices</h2>
      <p>You can update your profile at any time, and you can delete your account and all your documents from your settings.</p>
      <h2>Contact</h2>
      <p>For privacy questions, contact [privacy@example.com].</p>
    </StaticPage>
  );
}
