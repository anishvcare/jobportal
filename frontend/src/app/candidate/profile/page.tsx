"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Alert } from "@/components/ui/Alert";
import { Card, PageHeader } from "@/components/ui/Card";
import { PageLoader } from "@/components/ui/Spinner";
import { CompletenessMeter } from "@/components/candidate/CompletenessMeter";
import { ConsentStep } from "@/components/candidate/wizard/ConsentStep";
import { EducationStep } from "@/components/candidate/wizard/EducationStep";
import { ExperienceStep } from "@/components/candidate/wizard/ExperienceStep";
import { PassportStep } from "@/components/candidate/wizard/PassportStep";
import { PersonalStep } from "@/components/candidate/wizard/PersonalStep";
import { PreferencesStep } from "@/components/candidate/wizard/PreferencesStep";
import { SkillsLanguagesStep } from "@/components/candidate/wizard/SkillsLanguagesStep";
import { patchProfile, useLookups, useProfile } from "@/lib/candidate";
import { errorMessage } from "@/lib/errors";

const STEP_TITLES = [
  "Personal details",
  "Passport",
  "Education",
  "Experience",
  "Skills & languages",
  "Preferences",
  "Consent & finish",
];

const TOTAL = STEP_TITLES.length;

export default function ProfileWizardPage() {
  const router = useRouter();
  const { profile, isLoading, error } = useProfile();
  const { lookups, isLoading: lookupsLoading, error: lookupsError } = useLookups();
  const [step, setStep] = useState<number | null>(null);

  // Resume at the furthest step reached (clamped to a real step index).
  // Derived during render (not in an effect) once the profile has loaded.
  if (step === null && profile) {
    setStep(Math.min(Math.max(profile.wizard_step, 0), TOTAL - 1));
  }

  if (isLoading || lookupsLoading || step === null || !profile || !lookups) {
    if (error || lookupsError) {
      return (
        <div className="mx-auto max-w-lg">
          <Alert tone="error">{errorMessage(error ?? lookupsError)}</Alert>
        </div>
      );
    }
    return <PageLoader />;
  }

  const goTo = (next: number) => {
    const clamped = Math.min(Math.max(next, 0), TOTAL - 1);
    setStep(clamped);
    // Persist the furthest step reached so a reload resumes here.
    if (clamped > profile.wizard_step) {
      void patchProfile({ wizard_step: clamped }).catch(() => undefined);
    }
    if (typeof window !== "undefined") window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const onNext = () => goTo(step + 1);
  const onBack = () => goTo(step - 1);
  const onFinish = () => router.push("/candidate");

  const stepProps = { profile, lookups, step, totalSteps: TOTAL, onNext, onBack };

  return (
    <>
      <PageHeader
        title="Your profile"
        description={`Step ${step + 1} of ${TOTAL}: ${STEP_TITLES[step]}`}
      />

      <div className="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div className="order-2 space-y-4 lg:order-1">
          <ol className="flex flex-wrap gap-1.5" aria-label="Wizard steps">
            {STEP_TITLES.map((title, index) => (
              <li key={title}>
                <button
                  type="button"
                  onClick={() => goTo(index)}
                  aria-current={index === step ? "step" : undefined}
                  className={`h-2.5 w-8 rounded-full transition-colors ${
                    index === step ? "bg-brand-600" : index < step ? "bg-brand-300" : "bg-slate-200"
                  }`}
                >
                  <span className="sr-only">
                    Step {index + 1}: {title}
                  </span>
                </button>
              </li>
            ))}
          </ol>

          <Card>
            {step === 0 && <PersonalStep {...stepProps} />}
            {step === 1 && <PassportStep {...stepProps} />}
            {step === 2 && <EducationStep {...stepProps} />}
            {step === 3 && <ExperienceStep {...stepProps} />}
            {step === 4 && <SkillsLanguagesStep {...stepProps} />}
            {step === 5 && <PreferencesStep {...stepProps} />}
            {step === 6 && (
              <ConsentStep
                profile={profile}
                step={step}
                totalSteps={TOTAL}
                onFinish={onFinish}
                onBack={onBack}
              />
            )}
          </Card>
        </div>

        <aside className="order-1 lg:order-2">
          <Card>
            <CompletenessMeter completeness={profile.completeness} />
          </Card>
        </aside>
      </div>
    </>
  );
}
