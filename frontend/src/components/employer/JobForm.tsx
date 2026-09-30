"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Field, Select, TextArea, TextInput } from "@/components/ui/Field";
import { PageLoader } from "@/components/ui/Spinner";
import { createJob, updateJob, useDistricts, useLookups, useStates, type JobInput } from "@/lib/employer";
import { errorMessage } from "@/lib/errors";
import type { EmployerJob } from "@/lib/types";

const schema = z.object({
  title: z.string().trim().min(1, "Please enter a job title.").max(150),
  description: z.string().trim().min(1, "Please enter a description."),
  job_category_id: z.string().min(1, "Please pick a trade."),
  country_id: z.string().optional().or(z.literal("")),
  state_id: z.string().optional().or(z.literal("")),
  district_id: z.string().optional().or(z.literal("")),
  city: z.string().trim().max(120).optional().or(z.literal("")),
  education_level_id: z.string().optional().or(z.literal("")),
  experience_min: z.string().optional().or(z.literal("")),
  experience_max: z.string().optional().or(z.literal("")),
  vacancies: z.string().optional().or(z.literal("")),
  salary_min: z.string().optional().or(z.literal("")),
  salary_max: z.string().optional().or(z.literal("")),
  salary_currency: z.string().trim().length(3, "Use a 3-letter currency code.").optional().or(z.literal("")),
  deadline: z.string().optional().or(z.literal("")),
});

type Values = z.infer<typeof schema>;

const toNum = (v?: string): number | null => (v && v !== "" ? Number(v) : null);
const orNull = (v?: string): string | null => (v && v !== "" ? v : null);

/** Shared create/edit form for an employer job posting. */
export function JobForm({ job }: { job?: EmployerJob }) {
  const router = useRouter();
  const { lookups, isLoading: lookupsLoading } = useLookups();
  const [skillIds, setSkillIds] = useState<number[]>(job?.skills.map((s) => s.id) ?? []);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      title: job?.title ?? "",
      description: job?.description ?? "",
      job_category_id: job?.job_category_id ? String(job.job_category_id) : "",
      country_id: job?.country_id ? String(job.country_id) : "",
      state_id: job?.state_id ? String(job.state_id) : "",
      district_id: job?.district_id ? String(job.district_id) : "",
      city: job?.city ?? "",
      education_level_id: job?.education_level_id ? String(job.education_level_id) : "",
      experience_min: job?.experience_min != null ? String(job.experience_min) : "",
      experience_max: job?.experience_max != null ? String(job.experience_max) : "",
      vacancies: job?.vacancies != null ? String(job.vacancies) : "1",
      salary_min: job?.salary_min != null ? String(job.salary_min) : "",
      salary_max: job?.salary_max != null ? String(job.salary_max) : "",
      salary_currency: job?.salary_currency ?? "INR",
      deadline: job?.deadline ?? "",
    },
  });

  const countryValue = useWatch({ control, name: "country_id" });
  const stateValue = useWatch({ control, name: "state_id" });
  const countryId = toNum(countryValue);
  const selectedCountry = lookups?.countries.find((c) => c.id === countryId) ?? null;
  const hasStates = selectedCountry?.has_states ?? false;
  const stateId = toNum(stateValue);
  const { states } = useStates(hasStates ? countryId : null);
  const { districts } = useDistricts(hasStates && stateId ? stateId : null);

  const tradeGroups = lookups?.job_categories ?? [];
  const educationLevels = [...(lookups?.education_levels ?? [])].sort((a, b) => a.rank - b.rank);
  // Skills are keyed by id. There is no public skills lookup (skills are created
  // on demand by candidates), so the selectable set is the skills already
  // attached to this job. Employers can therefore keep or drop existing skills.
  const jobSkills = job?.skills ?? [];

  const onSubmit = handleSubmit(async (values) => {
    setSaving(true);
    setSaveError(null);
    const payload: JobInput = {
      title: values.title.trim(),
      description: values.description.trim(),
      job_category_id: Number(values.job_category_id),
      country_id: toNum(values.country_id),
      state_id: hasStates ? toNum(values.state_id) : null,
      district_id: hasStates ? toNum(values.district_id) : null,
      city: hasStates ? null : orNull(values.city),
      education_level_id: toNum(values.education_level_id),
      experience_min: toNum(values.experience_min),
      experience_max: toNum(values.experience_max),
      vacancies: toNum(values.vacancies),
      salary_min: toNum(values.salary_min),
      salary_max: toNum(values.salary_max),
      salary_currency: orNull(values.salary_currency)?.toUpperCase() ?? null,
      deadline: orNull(values.deadline),
      skill_ids: skillIds,
    };
    try {
      if (job) await updateJob(job.id, payload);
      else await createJob(payload);
      router.push("/employer/jobs");
    } catch (err) {
      setSaveError(errorMessage(err));
      setSaving(false);
    }
  });

  if (lookupsLoading && !lookups) return <PageLoader label="Loading form…" />;

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <Field label="Job title" htmlFor="title" error={errors.title?.message}>
        <TextInput id="title" maxLength={150} {...register("title")} />
      </Field>

      <Field label="Description" htmlFor="description" error={errors.description?.message}>
        <TextArea id="description" rows={6} {...register("description")} />
      </Field>

      <Field label="Trade" htmlFor="job_category_id" error={errors.job_category_id?.message}>
        <Select id="job_category_id" {...register("job_category_id")}>
          <option value="">Select a trade…</option>
          {tradeGroups.map((group) => (
            <optgroup key={group.id} label={group.name}>
              {group.trades.map((trade) => (
                <option key={trade.id} value={trade.id}>
                  {trade.name}
                </option>
              ))}
            </optgroup>
          ))}
        </Select>
      </Field>

      <Field label="Country" htmlFor="country_id" error={errors.country_id?.message}>
        <Select id="country_id" {...register("country_id")}>
          <option value="">Select country…</option>
          {lookups?.countries.map((country) => (
            <option key={country.id} value={country.id}>
              {country.name}
            </option>
          ))}
        </Select>
      </Field>

      {hasStates ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="State / province" htmlFor="state_id" error={errors.state_id?.message}>
            <Select id="state_id" {...register("state_id")}>
              <option value="">Select…</option>
              {states.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="District" htmlFor="district_id" error={errors.district_id?.message}>
            <Select id="district_id" {...register("district_id")} disabled={!stateId}>
              <option value="">Select…</option>
              {districts.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
      ) : (
        <Field label="City / town" htmlFor="city" error={errors.city?.message}>
          <TextInput id="city" {...register("city")} />
        </Field>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Minimum qualification" htmlFor="education_level_id" error={errors.education_level_id?.message}>
          <Select id="education_level_id" {...register("education_level_id")}>
            <option value="">No minimum</option>
            {educationLevels.map((level) => (
              <option key={level.id} value={level.id}>
                {level.name}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Vacancies" htmlFor="vacancies" error={errors.vacancies?.message}>
          <TextInput id="vacancies" type="number" min={1} {...register("vacancies")} />
        </Field>
      </div>

      <Field label="Experience (years)" htmlFor="experience_min" error={errors.experience_min?.message ?? errors.experience_max?.message}>
        <div className="flex items-center gap-2">
          <TextInput id="experience_min" type="number" min={0} max={80} placeholder="Min" {...register("experience_min")} aria-label="Minimum experience" />
          <span className="text-slate-400">–</span>
          <TextInput type="number" min={0} max={80} placeholder="Max" {...register("experience_max")} aria-label="Maximum experience" />
        </div>
      </Field>

      <div className="grid gap-4 sm:grid-cols-[1fr_1fr_8rem]">
        <Field label="Salary min" htmlFor="salary_min" error={errors.salary_min?.message}>
          <TextInput id="salary_min" type="number" min={0} placeholder="Min" {...register("salary_min")} />
        </Field>
        <Field label="Salary max" htmlFor="salary_max" error={errors.salary_max?.message}>
          <TextInput id="salary_max" type="number" min={0} placeholder="Max" {...register("salary_max")} />
        </Field>
        <Field label="Currency" htmlFor="salary_currency" error={errors.salary_currency?.message}>
          <TextInput id="salary_currency" maxLength={3} placeholder="INR" {...register("salary_currency")} />
        </Field>
      </div>

      <Field label="Application deadline" htmlFor="deadline" error={errors.deadline?.message}>
        <TextInput id="deadline" type="date" {...register("deadline")} />
      </Field>

      {jobSkills.length > 0 && (
        <Field label="Required skills" htmlFor="skills" hint="Tick the skills to keep on this job.">
          <ul className="flex flex-wrap gap-2">
            {jobSkills.map((skill) => {
              const checked = skillIds.includes(skill.id);
              return (
                <li key={skill.id}>
                  <label
                    className={`inline-flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1 text-sm ${
                      checked ? "bg-brand-50 text-brand-800" : "bg-slate-100 text-slate-500 line-through"
                    }`}
                  >
                    <input
                      type="checkbox"
                      className="h-4 w-4 accent-brand-600"
                      checked={checked}
                      onChange={(e) =>
                        setSkillIds((prev) =>
                          e.target.checked ? [...prev, skill.id] : prev.filter((id) => id !== skill.id),
                        )
                      }
                    />
                    {skill.name}
                  </label>
                </li>
              );
            })}
          </ul>
        </Field>
      )}

      {saveError && <Alert tone="error">{saveError}</Alert>}

      <div className="flex gap-2">
        <Button type="submit" loading={saving}>
          {job ? "Save changes" : "Create job"}
        </Button>
        <ButtonLink href="/employer/jobs" variant="secondary">
          Cancel
        </ButtonLink>
      </div>
    </form>
  );
}
