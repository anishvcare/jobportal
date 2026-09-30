"use client";

import { useMemo } from "react";
import { Button } from "@/components/ui/Button";
import { Field, Select, TextInput } from "@/components/ui/Field";
import { useAdminJobs, useDistricts, useLookups, useStates } from "@/lib/admin";
import type { CandidateSearchParams } from "@/lib/admin";

/** Convert an input value to a number filter, or undefined when blank. */
function num(value: string): number | undefined {
  if (value === "") return undefined;
  const n = Number(value);
  return Number.isFinite(n) ? n : undefined;
}

/**
 * The admin candidate search filter form. Filters are held in the parent as a
 * single CandidateSearchParams object; each control patches one key. State and
 * district selects are driven by the shared location hooks (India-first: the
 * candidate profile flow uses a single default country, so we resolve states by
 * the chosen state's implicit country via the location hooks the profile uses).
 */
export function CandidateFilters({
  filters,
  onChange,
  onReset,
  countryId,
}: {
  filters: CandidateSearchParams;
  onChange: (patch: Partial<CandidateSearchParams>) => void;
  onReset: () => void;
  /** Country whose states seed the state dropdown (defaults to the first lookup country). */
  countryId: number | null;
}) {
  const { lookups } = useLookups();
  const { states } = useStates(countryId);
  const { districts } = useDistricts(filters.state_id ?? null);
  // Populate the "applied to job" filter from the admin jobs list.
  const { jobs } = useAdminJobs({ per_page: 100 });

  // Flatten trades from the grouped job categories for the trade select.
  const tradeGroups = lookups?.job_categories ?? [];
  const educationLevels = useMemo(
    () => [...(lookups?.education_levels ?? [])].sort((a, b) => a.rank - b.rank),
    [lookups],
  );
  const languages = lookups?.languages ?? [];

  return (
    <form
      className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
      onSubmit={(event) => event.preventDefault()}
      aria-label="Candidate filters"
    >
      <div className="sm:col-span-2 lg:col-span-3">
        <Field label="Keyword (name or skill)" htmlFor="f-keyword">
          <TextInput
            id="f-keyword"
            type="search"
            placeholder="e.g. welder, Priya"
            value={filters.keyword ?? ""}
            onChange={(e) => onChange({ keyword: e.target.value || undefined })}
          />
        </Field>
      </div>

      <Field label="Education level" htmlFor="f-education">
        <Select
          id="f-education"
          value={filters.education_level_id ?? ""}
          onChange={(e) => onChange({ education_level_id: num(e.target.value) })}
        >
          <option value="">Any</option>
          {educationLevels.map((level) => (
            <option key={level.id} value={level.id}>
              {level.name}
            </option>
          ))}
        </Select>
      </Field>

      <Field label="Trade" htmlFor="f-trade">
        <Select
          id="f-trade"
          value={filters.job_category_id ?? ""}
          onChange={(e) => onChange({ job_category_id: num(e.target.value) })}
        >
          <option value="">Any</option>
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

      <Field label="Gender" htmlFor="f-gender">
        <Select id="f-gender" value={filters.gender ?? ""} onChange={(e) => onChange({ gender: e.target.value || undefined })}>
          <option value="">Any</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
          <option value="other">Other</option>
        </Select>
      </Field>

      <Field label="Experience (years)" htmlFor="f-exp-min">
        <div className="flex items-center gap-2">
          <TextInput
            id="f-exp-min"
            type="number"
            min={0}
            max={80}
            placeholder="Min"
            value={filters.experience_min ?? ""}
            onChange={(e) => onChange({ experience_min: num(e.target.value) })}
            aria-label="Minimum years of experience"
          />
          <span className="text-slate-400">–</span>
          <TextInput
            type="number"
            min={0}
            max={80}
            placeholder="Max"
            value={filters.experience_max ?? ""}
            onChange={(e) => onChange({ experience_max: num(e.target.value) })}
            aria-label="Maximum years of experience"
          />
        </div>
      </Field>

      <Field label="Age" htmlFor="f-age-min">
        <div className="flex items-center gap-2">
          <TextInput
            id="f-age-min"
            type="number"
            min={0}
            max={120}
            placeholder="Min"
            value={filters.age_min ?? ""}
            onChange={(e) => onChange({ age_min: num(e.target.value) })}
            aria-label="Minimum age"
          />
          <span className="text-slate-400">–</span>
          <TextInput
            type="number"
            min={0}
            max={120}
            placeholder="Max"
            value={filters.age_max ?? ""}
            onChange={(e) => onChange({ age_max: num(e.target.value) })}
            aria-label="Maximum age"
          />
        </div>
      </Field>

      <Field label="State" htmlFor="f-state">
        <Select
          id="f-state"
          value={filters.state_id ?? ""}
          onChange={(e) => onChange({ state_id: num(e.target.value), district_id: undefined })}
        >
          <option value="">Any</option>
          {states.map((state) => (
            <option key={state.id} value={state.id}>
              {state.name}
            </option>
          ))}
        </Select>
      </Field>

      <Field label="District" htmlFor="f-district">
        <Select
          id="f-district"
          value={filters.district_id ?? ""}
          disabled={!filters.state_id}
          onChange={(e) => onChange({ district_id: num(e.target.value) })}
        >
          <option value="">Any</option>
          {districts.map((district) => (
            <option key={district.id} value={district.id}>
              {district.name}
            </option>
          ))}
        </Select>
      </Field>

      <Field label="Passport" htmlFor="f-passport">
        <Select
          id="f-passport"
          value={filters.passport ?? "any"}
          onChange={(e) => onChange({ passport: (e.target.value as CandidateSearchParams["passport"]) })}
        >
          <option value="any">Any</option>
          <option value="has">Has passport</option>
          <option value="valid">Valid passport</option>
        </Select>
      </Field>

      <Field label="Min. profile completeness (%)" htmlFor="f-completeness">
        <TextInput
          id="f-completeness"
          type="number"
          min={0}
          max={100}
          placeholder="0"
          value={filters.completeness_min ?? ""}
          onChange={(e) => onChange({ completeness_min: num(e.target.value) })}
        />
      </Field>

      <Field label="Applied to job" htmlFor="f-applied-job">
        <Select
          id="f-applied-job"
          value={filters.applied_job_id ?? ""}
          onChange={(e) => onChange({ applied_job_id: num(e.target.value) })}
        >
          <option value="">Any</option>
          {jobs.map((job) => (
            <option key={job.id} value={job.id}>
              {job.title}
            </option>
          ))}
        </Select>
      </Field>

      <Field label="Languages" htmlFor="f-languages">
        <Select
          id="f-languages"
          multiple
          className="min-h-24"
          value={(filters.language_ids ?? []).map(String)}
          onChange={(e) =>
            onChange({
              language_ids: Array.from(e.target.selectedOptions, (o) => Number(o.value)),
            })
          }
        >
          {languages.map((language) => (
            <option key={language.id} value={language.id}>
              {language.name}
            </option>
          ))}
        </Select>
      </Field>

      <div className="flex items-end sm:col-span-2 lg:col-span-3">
        <Button type="button" variant="ghost" onClick={onReset}>
          Clear filters
        </Button>
      </div>
    </form>
  );
}
