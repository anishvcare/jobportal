"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMemo, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/Button";
import { Field, Select, TextArea, TextInput } from "@/components/ui/Field";
import type { CandidateProfile, Experience, Lookups, NamedItem } from "@/lib/types";
import { createExperience, deleteExperience, updateExperience } from "@/lib/candidate";
import { StepError, useSaver } from "./shared";
import { StepFooter } from "./StepFooter";

const schema = z
  .object({
    job_title: z.string().trim().min(1, "Please enter a job title.").max(191),
    company: z.string().trim().max(191).optional().or(z.literal("")),
    job_category_id: z.string().optional().or(z.literal("")),
    start_date: z.string().optional().or(z.literal("")),
    end_date: z.string().optional().or(z.literal("")),
    is_current: z.boolean(),
    description: z.string().trim().max(2000).optional().or(z.literal("")),
  })
  .refine((v) => !v.start_date || !v.end_date || v.end_date >= v.start_date, {
    message: "End date must be after the start date.",
    path: ["end_date"],
  });

type Values = z.infer<typeof schema>;

const toNum = (v?: string): number | null => (v && v !== "" ? Number(v) : null);
const orNull = (v?: string): string | null => (v && v !== "" ? v.trim() : null);

interface Trade extends NamedItem {
  group: string;
}

function useTrades(lookups: Lookups): Trade[] {
  return useMemo(
    () =>
      lookups.job_categories.flatMap((group) =>
        group.trades.map((trade) => ({ ...trade, group: group.name })),
      ),
    [lookups],
  );
}

function ExperienceForm({
  trades,
  initial,
  onSave,
  onCancel,
}: {
  trades: Trade[];
  initial?: Experience;
  onSave: (values: Values) => Promise<void>;
  onCancel?: () => void;
}) {
  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      job_title: initial?.job_title ?? "",
      company: initial?.company ?? "",
      job_category_id: initial?.job_category_id ? String(initial.job_category_id) : "",
      start_date: initial?.start_date ?? "",
      end_date: initial?.end_date ?? "",
      is_current: initial?.is_current ?? false,
      description: initial?.description ?? "",
    },
  });

  const isCurrent = useWatch({ control, name: "is_current" });

  return (
    <form onSubmit={handleSubmit(onSave)} className="space-y-3 rounded-lg border border-slate-200 p-4" noValidate>
      <Field label="Job title" htmlFor="exp-title" error={errors.job_title?.message}>
        <TextInput id="exp-title" {...register("job_title")} />
      </Field>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Company" htmlFor="exp-company" error={errors.company?.message}>
          <TextInput id="exp-company" {...register("company")} />
        </Field>
        <Field label="Trade" htmlFor="exp-trade" error={errors.job_category_id?.message}>
          <Select id="exp-trade" {...register("job_category_id")}>
            <option value="">Select…</option>
            {trades.map((trade) => (
              <option key={trade.id} value={trade.id}>
                {trade.name} ({trade.group})
              </option>
            ))}
          </Select>
        </Field>
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Start date" htmlFor="exp-start" error={errors.start_date?.message}>
          <TextInput id="exp-start" type="date" {...register("start_date")} />
        </Field>
        <Field label="End date" htmlFor="exp-end" error={errors.end_date?.message}>
          <TextInput id="exp-end" type="date" disabled={isCurrent} {...register("end_date")} />
        </Field>
      </div>
      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" className="h-4 w-4 accent-brand-600" {...register("is_current")} />
        I currently work here
      </label>
      <Field label="Description" htmlFor="exp-desc" error={errors.description?.message}>
        <TextArea id="exp-desc" rows={2} {...register("description")} />
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

export function ExperienceStep({
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
  const trades = useTrades(lookups);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [adding, setAdding] = useState(false);

  const payload = (v: Values) => ({
    job_title: v.job_title.trim(),
    company: orNull(v.company),
    job_category_id: toNum(v.job_category_id),
    start_date: orNull(v.start_date),
    end_date: v.is_current ? null : orNull(v.end_date),
    is_current: v.is_current,
    description: orNull(v.description),
  });

  return (
    <div className="space-y-4">
      <p className="text-sm text-slate-600">Add your work experience. This step is optional.</p>

      {profile.experiences.length > 0 && (
        <ul className="space-y-2">
          {profile.experiences.map((exp) =>
            editingId === exp.id ? (
              <li key={exp.id}>
                <ExperienceForm
                  trades={trades}
                  initial={exp}
                  onSave={async (v) => {
                    await run(() => updateExperience(exp.id, payload(v)));
                    setEditingId(null);
                  }}
                  onCancel={() => setEditingId(null)}
                />
              </li>
            ) : (
              <li key={exp.id} className="flex items-center justify-between rounded-lg border border-slate-200 p-3">
                <div className="min-w-0">
                  <p className="truncate font-medium text-slate-800">{exp.job_title}</p>
                  <p className="truncate text-sm text-slate-500">
                    {[exp.company, exp.is_current ? "Current" : exp.end_date].filter(Boolean).join(" · ") || "—"}
                  </p>
                </div>
                <div className="flex shrink-0 gap-1">
                  <Button type="button" variant="ghost" onClick={() => setEditingId(exp.id)}>
                    Edit
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    className="text-red-700"
                    onClick={() => run(() => deleteExperience(exp.id))}
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
        <ExperienceForm
          trades={trades}
          onSave={async (v) => {
            const ok = await run(() => createExperience(payload(v)));
            if (ok) setAdding(false);
          }}
          onCancel={() => setAdding(false)}
        />
      ) : (
        <Button type="button" variant="secondary" onClick={() => setAdding(true)}>
          + Add experience
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
