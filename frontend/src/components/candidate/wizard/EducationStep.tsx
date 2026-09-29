"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/Button";
import { Field, Select, TextInput } from "@/components/ui/Field";
import type { CandidateProfile, Education, Lookups } from "@/lib/types";
import { createEducation, deleteEducation, updateEducation } from "@/lib/candidate";
import { StepError, useSaver } from "./shared";
import { StepFooter } from "./StepFooter";

const currentYear = new Date().getFullYear();

const schema = z.object({
  education_level_id: z.string().optional().or(z.literal("")),
  institution: z.string().trim().max(191).optional().or(z.literal("")),
  field_of_study: z.string().trim().max(191).optional().or(z.literal("")),
  year_completed: z
    .string()
    .optional()
    .or(z.literal(""))
    .refine((v) => !v || (Number(v) >= 1950 && Number(v) <= currentYear + 1), {
      message: `Enter a year between 1950 and ${currentYear + 1}.`,
    }),
});

type Values = z.infer<typeof schema>;

const toNum = (v?: string): number | null => (v && v !== "" ? Number(v) : null);
const orNull = (v?: string): string | null => (v && v !== "" ? v.trim() : null);

function EducationForm({
  lookups,
  initial,
  onSave,
  onCancel,
}: {
  lookups: Lookups;
  initial?: Education;
  onSave: (values: Values) => Promise<void>;
  onCancel?: () => void;
}) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      education_level_id: initial?.education_level_id ? String(initial.education_level_id) : "",
      institution: initial?.institution ?? "",
      field_of_study: initial?.field_of_study ?? "",
      year_completed: initial?.year_completed ? String(initial.year_completed) : "",
    },
  });

  return (
    <form onSubmit={handleSubmit(onSave)} className="space-y-3 rounded-lg border border-slate-200 p-4" noValidate>
      <Field label="Level" htmlFor="edu-level" error={errors.education_level_id?.message}>
        <Select id="edu-level" {...register("education_level_id")}>
          <option value="">Select…</option>
          {lookups.education_levels.map((level) => (
            <option key={level.id} value={level.id}>
              {level.name}
            </option>
          ))}
        </Select>
      </Field>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Institution" htmlFor="edu-institution" error={errors.institution?.message}>
          <TextInput id="edu-institution" {...register("institution")} />
        </Field>
        <Field label="Field of study" htmlFor="edu-field" error={errors.field_of_study?.message}>
          <TextInput id="edu-field" {...register("field_of_study")} />
        </Field>
      </div>
      <Field label="Year completed" htmlFor="edu-year" error={errors.year_completed?.message}>
        <TextInput id="edu-year" inputMode="numeric" placeholder={String(currentYear)} {...register("year_completed")} />
      </Field>
      <div className="flex gap-2">
        <Button type="submit" loading={isSubmitting}>
          {initial ? "Update" : "Add"}
        </Button>
        {onCancel && (
          <Button type="button" variant="ghost" onClick={onCancel}>
            Cancel
          </Button>
        )}
      </div>
    </form>
  );
}

export function EducationStep({
  profile,
  lookups,
  step,
  totalSteps,
  onNext,
  onBack,
}: {
  profile: CandidateProfile;
  lookups: Lookups;
  step: number;
  totalSteps: number;
  onNext: () => void;
  onBack: () => void;
}) {
  const { error, run, setError } = useSaver();
  const [editingId, setEditingId] = useState<number | null>(null);
  const [adding, setAdding] = useState(profile.educations.length === 0);

  const levelName = (id: number | null) =>
    lookups.education_levels.find((l) => l.id === id)?.name ?? "Education";

  const payload = (v: Values) => ({
    education_level_id: toNum(v.education_level_id),
    institution: orNull(v.institution),
    field_of_study: orNull(v.field_of_study),
    year_completed: toNum(v.year_completed),
  });

  return (
    <div className="space-y-4">
      <p className="text-sm text-slate-600">Add each qualification you have completed.</p>

      {profile.educations.length > 0 && (
        <ul className="space-y-2">
          {profile.educations.map((edu) =>
            editingId === edu.id ? (
              <li key={edu.id}>
                <EducationForm
                  lookups={lookups}
                  initial={edu}
                  onSave={async (v) => {
                    await run(() => updateEducation(edu.id, payload(v)));
                    setEditingId(null);
                  }}
                  onCancel={() => setEditingId(null)}
                />
              </li>
            ) : (
              <li key={edu.id} className="flex items-center justify-between rounded-lg border border-slate-200 p-3">
                <div className="min-w-0">
                  <p className="truncate font-medium text-slate-800">{levelName(edu.education_level_id)}</p>
                  <p className="truncate text-sm text-slate-500">
                    {[edu.institution, edu.field_of_study, edu.year_completed].filter(Boolean).join(" · ") || "—"}
                  </p>
                </div>
                <div className="flex shrink-0 gap-1">
                  <Button type="button" variant="ghost" onClick={() => setEditingId(edu.id)}>
                    Edit
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    className="text-red-700"
                    onClick={() => run(() => deleteEducation(edu.id))}
                  >
                    Delete
                  </Button>
                </div>
              </li>
            ),
          )}
        </ul>
      )}

      {adding ? (
        <EducationForm
          lookups={lookups}
          onSave={async (v) => {
            const ok = await run(() => createEducation(payload(v)));
            if (ok) setAdding(false);
          }}
          onCancel={profile.educations.length > 0 ? () => setAdding(false) : undefined}
        />
      ) : (
        <Button type="button" variant="secondary" onClick={() => setAdding(true)}>
          + Add education
        </Button>
      )}

      <StepError message={error} />
      <form
        onSubmit={(e) => {
          e.preventDefault();
          setError(null);
          onNext();
        }}
      >
        <StepFooter step={step} totalSteps={totalSteps} onBack={onBack} />
      </form>
    </div>
  );
}
