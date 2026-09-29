import type { Metadata } from "next";
import { StaticPage } from "@/components/layout/StaticPage";

export const metadata: Metadata = { title: "Terms of Use" };

export default function TermsPage() {
  return (
    <StaticPage title="Terms of Use" updated="[date]">
      <p>[Placeholder text: have these terms reviewed by a legal professional before launch.]</p>
      <h2>Using Nexus Flow</h2>
      <p>By signing in you agree to provide accurate information and to use the platform only for genuine job searching or hiring.</p>
      <h2>Candidates</h2>
      <p>You are responsible for making sure the documents you upload are your own, genuine and up to date.</p>
      <h2>Employers</h2>
      <p>Employer accounts must be approved before posting jobs. Job posts must be genuine and lawful. We may hide or remove any post.</p>
      <h2>Fees</h2>
      <p>[Describe any fees, or state that the service is free for candidates.]</p>
      <h2>Governing law</h2>
      <p>These terms are governed by the laws of India. Courts in Kottayam, Kerala have jurisdiction.</p>
    </StaticPage>
  );
}
