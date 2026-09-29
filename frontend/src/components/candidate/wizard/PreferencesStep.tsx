"use client";

import { useMemo, useState } from "react";
import { Alert } from "@/components/ui/Alert";
import type { CandidateProfile, Lookups, NamedItem } from "@/lib/types";
import { syncPreferredCategories, syncPreferredCountries } from "@/lib/candidate";
import { SaveStatus, StepError, useSaver } from "./shared";
import { StepFooter } from "./StepFooter";

const MAX = 3;

interface Trade extends NamedItem {
  group: string;
}

function CappedList({
  title,
  items,
  selected,
  onToggle,
  label,
}: {
  title: string;
  items: { id: number; name: string; group?: string }[];
  selected: number[];
  onToggle: (id: number) => void;
  label: string;
}) {
  return (
    <div>
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium text-slate-800">{title}</p>
        <span className="text-xs text-slate-500">
          {selected.length}/{MAX} selected
        </span>
      </div>
      <ul className="mt-2 grid gap-2 sm:grid-cols-2">
        {items.map((item) => {
          const isSelected = selected.includes(item.id);
          const atMax = selected.length >= MAX && !isSelected;
          return (
            <li key={item.id}>
              <label
                className={`flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm ${
                  isSelected ? "border-brand-600 bg-brand-50" : "border-slate-300"
                } ${atMax ? "cursor-not-allowed opacity-50" : ""}`}
              >
                <input
                  type="checkbox"
                  className="h-4 w-4 accent-brand-600"
                  checked={isSelected}
                  disabled={atMax}
                  onChange={() => onToggle(item.id)}
                  aria-label={`${label}: ${item.name}`}
                />
                <span>
                  {item.name}
                  {item.group && <span className="text-slate-500"> · {item.group}</span>}
                </span>
              </label>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

export function PreferencesStep({
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
  const { state, error, run, setError } = useSaver();
  const [categories, setCategories] = useState<number[]>(profile.preferred_categories.map((c) => c.id));
  const [countries, setCountries] = useState<number[]>(profile.preferred_countries.map((c) => c.id));
  const [notice, setNotice] = useState<string | null>(null);

  const trades = useMemo<Trade[]>(
    () => lookups.job_categories.flatMap((g) => g.trades.map((t) => ({ ...t, group: g.name }))),
    [lookups],
  );

  const toggle = (list: number[], setList: (v: number[]) => void, id: number) => {
    setNotice(null);
    if (list.includes(id)) {
      setList(list.filter((x) => x !== id));
    } else if (list.length >= MAX) {
      setNotice(`You can pick up to ${MAX} only.`);
    } else {
      setList([...list, id]);
    }
  };

  const onContinue = async () => {
    setError(null);
    const ok = await run(async () => {
      await syncPreferredCategories(categories);
      await syncPreferredCountries(countries);
    });
    if (ok) onNext();
  };

  return (
    <form
      className="space-y-6"
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        void onContinue();
      }}
    >
      <p className="text-sm text-slate-600">Choose up to {MAX} trades and {MAX} countries you would like to work in.</p>

      <CappedList
        title="Preferred trades"
        label="Preferred trade"
        items={trades}
        selected={categories}
        onToggle={(id) => toggle(categories, setCategories, id)}
      />

      <CappedList
        title="Preferred countries"
        label="Preferred country"
        items={lookups.countries.map((c) => ({ id: c.id, name: c.name }))}
        selected={countries}
        onToggle={(id) => toggle(countries, setCountries, id)}
      />

      {notice && <Alert tone="warning">{notice}</Alert>}
      <StepError message={error} />
      <StepFooter step={step} totalSteps={totalSteps} onBack={onBack} status={<SaveStatus state={state} />} />
    </form>
  );
}
