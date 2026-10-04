import type { Metadata } from "next";
import { StaticPage } from "@/components/layout/StaticPage";

export const metadata: Metadata = {
  title: "Terms of Service",
  description:
    "The terms that govern your use of the NexusFlow Services LLP overseas recruitment and MBBS admission portal.",
};

export default function TermsPage() {
  return (
    <StaticPage title="Terms of Service" updated="4 October 2026">
      <p>
        These Terms of Service (&ldquo;Terms&rdquo;) govern your access to and use
        of the NexusFlow portal at{" "}
        <a href="https://nexusflowservices.com">nexusflowservices.com</a> and its
        related overseas recruitment and MBBS admission services (the
        &ldquo;Service&rdquo;), operated by NexusFlow Services LLP, a Limited
        Liability Partnership with its registered office at Door No 13/227,
        Kakkoor, Piravom, Muvattupuzha, Ernakulam, Kerala, India &ndash; 686662. By
        signing in to or using the Service, you agree to these Terms. If you do not
        agree, please do not use the Service.
      </p>

      <h2>1. Who can use the Service</h2>
      <p>
        The Service is intended for genuine job seekers, students seeking overseas
        MBBS admission, and genuine employers. You must be at least 18 years old
        and able to form a binding agreement to use the Service. The Service is not
        intended for and may not be used by anyone under 18.
      </p>

      <h2>2. Our role</h2>
      <p>
        NexusFlow Services LLP acts as a facilitator that connects candidates and
        students with employers and universities abroad, including in Russia,
        Israel, Vietnam and Uzbekistan. We assist with applications and
        documentation, but admission and hiring decisions are made solely by the
        relevant university or employer. We do not guarantee any job offer,
        admission, visa, or placement.
      </p>

      <h2>3. Your account</h2>
      <p>
        You sign in with your Google account. You are responsible for keeping your
        account secure and for all activity under it. You agree to provide
        accurate, current and complete information and to keep it up to date. We
        may suspend or close accounts that contain false information or that misuse
        the Service.
      </p>

      <h2>4. Candidates and students</h2>
      <ul>
        <li>
          You confirm that the documents and information you upload &mdash;
          including academic records for MBBS admission &mdash; are your own,
          genuine, lawful and up to date.
        </li>
        <li>
          You authorise us to share the documents required for a specific
          application with the relevant employer or university on your behalf.
        </li>
        <li>
          You are responsible for the content of your profile and the documents you
          choose to share.
        </li>
        <li>
          Applying through the Service does not guarantee a response, an interview,
          a job, an admission or a visa.
        </li>
      </ul>

      <h2>5. Employers</h2>
      <ul>
        <li>
          Employer accounts must be approved by our team before you can post jobs.
        </li>
        <li>
          Job posts must be genuine, lawful and non-discriminatory. You must not
          post misleading roles, request unlawful payment from candidates, or use
          the Service to collect data for any purpose other than genuine hiring.
        </li>
        <li>
          You may view only the profiles and resumes of candidates who apply to
          your own job posts, and you must use that information solely for
          recruitment. We may hide or remove any post that breaches these Terms.
        </li>
      </ul>

      <h2>6. Acceptable use</h2>
      <p>You agree not to:</p>
      <ul>
        <li>Use the Service for any unlawful, fraudulent or harmful purpose.</li>
        <li>
          Upload content that is false, misleading, offensive, or that infringes
          anyone else&rsquo;s rights.
        </li>
        <li>
          Attempt to access accounts, data or systems you are not authorised to
          access, or disrupt or probe the Service&rsquo;s security.
        </li>
        <li>
          Scrape, copy or harvest candidate, student or employer data except as the
          Service intends.
        </li>
      </ul>

      <h2>7. Fees</h2>
      <p>
        Any fees for recruitment, admission assistance or related services will be
        communicated to you clearly in advance, in a separate agreement or quote,
        before you incur them. Government, university, visa and third-party charges
        are not included in our service fees unless expressly stated.
      </p>

      <h2>8. Your content</h2>
      <p>
        You keep ownership of the information and documents you upload. You grant
        NexusFlow Services LLP a limited licence to store, process and share that
        content as needed to operate the Service and process your applications. Our
        handling of your personal information is described in our{" "}
        <a href="/privacy">Privacy Policy</a>.
      </p>

      <h2>9. Service availability</h2>
      <p>
        We work to keep the Service available and reliable, but we provide it
        &ldquo;as is&rdquo; and do not guarantee that it will be uninterrupted or
        error-free. We may change, suspend or discontinue parts of the Service at
        any time.
      </p>

      <h2>10. Limitation of liability</h2>
      <p>
        To the fullest extent permitted by law, NexusFlow Services LLP is not
        liable for any indirect or consequential loss, or for the acts or decisions
        of employers, universities, candidates or students who use the Service. We
        are a facilitator and are not a party to any employment or admission
        relationship, and we do not guarantee the conduct, qualifications, offers
        or decisions of any user, employer or university.
      </p>

      <h2>11. Termination</h2>
      <p>
        You may stop using the Service and delete your account at any time from
        your account settings. We may suspend or terminate your access if you
        breach these Terms or misuse the Service.
      </p>

      <h2>12. Changes to these Terms</h2>
      <p>
        We may update these Terms from time to time. When we do, we will revise the
        &ldquo;Last updated&rdquo; date above. Your continued use of the Service
        after changes take effect means you accept the updated Terms.
      </p>

      <h2>13. Governing law</h2>
      <p>
        These Terms are governed by the laws of India. The courts in Ernakulam,
        Kerala shall have jurisdiction over any dispute arising from them.
      </p>

      <h2>14. Contact</h2>
      <p>
        Questions about these Terms? Contact us at{" "}
        <a href="mailto:anishvcare@gmail.com">anishvcare@gmail.com</a>,
        NexusFlow Services LLP, Door No 13/227, Kakkoor, Piravom, Muvattupuzha,
        Ernakulam, Kerala, India &ndash; 686662.
      </p>
    </StaticPage>
  );
}
