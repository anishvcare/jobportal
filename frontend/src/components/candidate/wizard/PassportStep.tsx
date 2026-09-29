"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { Field, TextInput } from "@/components/ui/Field";
import type { CandidateProfile } from "@/lib/types";
import { patchProfile } from "@/lib/candidate";
import { SaveStatus, StepError, useSaver } from "./shared";
import { StepFooter } from "./StepFooter";

const schema = z.object({
  has_passport: z.boolean(),
  passport_number: z.string().trim().max(60).optional().or(z.literal("")),
  passport_expiry: z.string().optional().or(z.literal("")),
});

type Values = z.infer<typeof schema>;

const orNull = (v?: string): string | null => (v && v !== "" ? v : null);

export function PassportStep({
  profile,
  step,
  totalSteps,
  onNext,
  onBack,
}: {
  profile: CandidateProfile;
  step: number;
  totalSteps: number;
  onNext: () => void;
  onBack: () => void;
}) {
  const { state, error, run } = useSaver();
  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      has_passport: profile.has_passport,
      passport_number: profile.passport_number ?? "",
      passport_expiry: profile.passport_expiry ?? "",
    },
  });

  const hasPassport = useWatch({ control, name: "has_passport" });

  const onSubmit = handleSubmit(async (values) => {
    const ok = await run(() =>
      patchProfile({
        has_passport: values.has_passport,
        passport_number: values.has_passport ? orNull(values.passport_number) : null,
        passport_expiry: values.has_passport ? orNull(values.passport_expiry) : null,
        wizard_step: Math.max(profile.wizard_step, step + 1),
      }),
    );
    if (ok) onNext();
  });

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <label className="flex items-center gap-3 rounded-lg border border-slate-300 p-4">
        <input type="checkbox" className="h-4 w-4 accent-brand-600" {...register("has_passport")} />
        <span className="text-sm font-medium text-slate-800">I have a passport</span>
      </label>

      {hasPassport && (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="Passport number"
            htmlFor="passport_number"
            hint="Stored securely and encrypted."
            error={errors.passport_number?.message}
          >
            <TextInput id="passport_number" autoComplete="off" {...register("passport_number")} />
          </Field>
          <Field label="Expiry date" htmlFor="passport_expiry" error={errors.passport_expiry?.message}>
            <TextInput id="passport_expiry" type="date" {...register("passport_expiry")} />
          </Field>
        </div>
      )}

      <StepError message={error} />
      <StepFooter step={step} totalSteps={totalSteps} onBack={onBack} status={<SaveStatus state={state} />} />
    </form>
  );
}
