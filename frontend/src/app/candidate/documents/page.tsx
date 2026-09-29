"use client";

import type { ReactNode } from "react";
import { CompletenessMeter } from "@/components/candidate/CompletenessMeter";
import { PackDownloads } from "@/components/candidate/PackDownloads";
import { DocumentUploader } from "@/components/candidate/documents/DocumentUploader";
import { MultiDocumentSection } from "@/components/candidate/documents/MultiDocumentSection";
import { PassportSection } from "@/components/candidate/documents/PassportSection";
import { Alert } from "@/components/ui/Alert";
import { ButtonLink } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { PageLoader } from "@/components/ui/Spinner";
import { useProfile } from "@/lib/candidate";
import { errorMessage } from "@/lib/errors";
import type { DocumentDto, DocumentType } from "@/lib/types";

const PROFILE_PDF_INSTRUCTION =
  "Upload a profile PDF that includes your CV, photo, Aadhaar card, all pages of your passport (30 or 60), SSLC book, other qualification certificates and experience certificates.";

function DocSection({
  title,
  description,
  children,
}: {
  title: string;
  description?: string;
  children: ReactNode;
}) {
  return (
    <Card>
      <div className="mb-3">
        <h2 className="font-semibold text-slate-900">{title}</h2>
        {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
      </div>
      {children}
    </Card>
  );
}

export default function CandidateDocumentsPage() {
  const { profile, isLoading, error, mutate } = useProfile();

  if (isLoading) return <PageLoader label="Loading your documents…" />;
  if (error || !profile) {
    return (
      <>
        <PageHeader title="Documents" />
        <Alert tone="error">{error ? errorMessage(error) : "We couldn't load your documents."}</Alert>
      </>
    );
  }

  const documents = profile.documents ?? [];
  const single = (type: DocumentType): DocumentDto | undefined =>
    documents.find((doc) => doc.type === type);
  const many = (type: DocumentType): DocumentDto[] => documents.filter((doc) => doc.type === type);

  const refresh = () => {
    void mutate();
  };

  return (
    <>
      <PageHeader
        title="Your documents"
        description="Upload clear photos or scans. Images are compressed automatically; max 10 MB per file (50 MB for the profile PDF)."
        actions={
          <ButtonLink href="/candidate/profile" variant="secondary">
            Back to profile
          </ButtonLink>
        }
      />

      <div className="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div className="space-y-6 lg:order-1">
          <DocSection title="Passport-size photo" description="A clear, recent headshot.">
            <DocumentUploader type="photo" cameraCapture document={single("photo")} onChanged={refresh} />
          </DocSection>

          <DocSection title="Aadhaar card" description="Upload both the front and back.">
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <p className="mb-2 text-sm font-medium text-slate-700">Front</p>
                <DocumentUploader
                  type="aadhaar_front"
                  cameraCapture
                  document={single("aadhaar_front")}
                  onChanged={refresh}
                />
              </div>
              <div>
                <p className="mb-2 text-sm font-medium text-slate-700">Back</p>
                <DocumentUploader
                  type="aadhaar_back"
                  cameraCapture
                  document={single("aadhaar_back")}
                  onChanged={refresh}
                />
              </div>
            </div>
          </DocSection>

          <DocSection title="SSLC book" description="Your 10th-standard certificate/book.">
            <DocumentUploader type="sslc" cameraCapture document={single("sslc")} onChanged={refresh} />
          </DocSection>

          <DocSection
            title="Passport pages"
            description="Add every page as an image or PDF. Use the arrows to put them in order."
          >
            <PassportSection pages={many("passport")} onChanged={refresh} />
          </DocSection>

          <DocSection title="Education certificates" description="Add one or more certificates.">
            <MultiDocumentSection
              type="education_cert"
              documents={many("education_cert")}
              addLabel="Add education certificate"
              onChanged={refresh}
            />
          </DocSection>

          <DocSection title="Skill certificates" description="Add one or more certificates.">
            <MultiDocumentSection
              type="skill_cert"
              documents={many("skill_cert")}
              addLabel="Add skill certificate"
              onChanged={refresh}
            />
          </DocSection>

          <DocSection title="Experience certificates" description="Add one or more certificates.">
            <MultiDocumentSection
              type="experience_cert"
              documents={many("experience_cert")}
              addLabel="Add experience certificate"
              onChanged={refresh}
            />
          </DocSection>

          <DocSection title="CV (optional)" description="A PDF or image of your CV, if you have one.">
            <DocumentUploader type="cv" document={single("cv")} onChanged={refresh} />
          </DocSection>

          <DocSection title="Profile PDF">
            <p className="mb-3 text-sm text-slate-600">{PROFILE_PDF_INSTRUCTION}</p>
            <DocumentUploader type="profile_pdf" pdfOnly document={single("profile_pdf")} onChanged={refresh} />
          </DocSection>
        </div>

        <aside className="space-y-6 lg:order-2">
          <Card>
            <CompletenessMeter completeness={profile.completeness} />
          </Card>
          <PackDownloads />
        </aside>
      </div>
    </>
  );
}
