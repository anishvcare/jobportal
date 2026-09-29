"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Field, Select, TextInput } from "@/components/ui/Field";
import type { CandidateProfile, LanguageProficiency, Lookups } from "@/lib/types";
import { syncLanguages, syncSkills, type LanguageInput } from "@/lib/candidate";
import { SaveStatus, StepError, useSaver } from "./shared";
import { StepFooter } from "./StepFooter";

const PROFICIENCIES: LanguageProficiency[] = ["basic", "conversational", "fluent", "native"];

const proficiencyLabel = (p: LanguageProficiency) => p.charAt(0).toUpperCase() + p.slice(1);

export function SkillsLanguagesStep({
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

  const [skills, setSkills] = useState<string[]>(profile.skills.map((s) => s.name));
  const [skillInput, setSkillInput] = useState("");

  const [languages, setLanguages] = useState<Record<number, LanguageProficiency | "">>(
    () => Object.fromEntries(profile.languages.map((l) => [l.id, l.proficiency ?? ""])),
  );

  const addSkill = () => {
    const value = skillInput.trim();
    if (!value) return;
    if (!skills.some((s) => s.toLowerCase() === value.toLowerCase())) {
      setSkills((prev) => [...prev, value]);
    }
    setSkillInput("");
  };

  const removeSkill = (name: string) => setSkills((prev) => prev.filter((s) => s !== name));

  const toggleLanguage = (id: number) =>
    setLanguages((prev) => {
      const next = { ...prev };
      if (id in next) delete next[id];
      else next[id] = "";
      return next;
    });

  const onContinue = async () => {
    setError(null);
    const languagePayload: LanguageInput[] = Object.entries(languages).map(([id, proficiency]) => ({
      id: Number(id),
      proficiency: proficiency === "" ? null : proficiency,
    }));
    const ok = await run(async () => {
      await syncSkills(skills);
      await syncLanguages(languagePayload);
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
      <div>
        <Field label="Skills" htmlFor="skill-input" hint="Type a skill and press Add. Create your own if it isn't listed.">
          <div className="flex gap-2">
            <TextInput
              id="skill-input"
              value={skillInput}
              onChange={(e) => setSkillInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  e.preventDefault();
                  addSkill();
                }
              }}
              placeholder="e.g. Welding"
            />
            <Button type="button" variant="secondary" onClick={addSkill}>
              Add
            </Button>
          </div>
        </Field>
        {skills.length > 0 && (
          <ul className="mt-3 flex flex-wrap gap-2">
            {skills.map((name) => (
              <li key={name}>
                <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-sm text-brand-800">
                  {name}
                  <button
                    type="button"
                    aria-label={`Remove ${name}`}
                    onClick={() => removeSkill(name)}
                    className="text-brand-500 hover:text-brand-700"
                  >
                    &times;
                  </button>
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div>
        <p className="text-sm font-medium text-slate-800">Languages</p>
        <p className="text-xs text-slate-500">Tick the languages you know and pick how well you speak them.</p>
        <ul className="mt-3 space-y-2">
          {lookups.languages.map((language) => {
            const selected = language.id in languages;
            return (
              <li key={language.id} className="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-3">
                <label className="flex flex-1 items-center gap-2 text-sm text-slate-700">
                  <input
                    type="checkbox"
                    className="h-4 w-4 accent-brand-600"
                    checked={selected}
                    onChange={() => toggleLanguage(language.id)}
                  />
                  {language.name}
                </label>
                {selected && (
                  <Select
                    aria-label={`${language.name} proficiency`}
                    className="w-auto"
                    value={languages[language.id]}
                    onChange={(e) =>
                      setLanguages((prev) => ({
                        ...prev,
                        [language.id]: e.target.value as LanguageProficiency | "",
                      }))
                    }
                  >
                    <option value="">Proficiency…</option>
                    {PROFICIENCIES.map((p) => (
                      <option key={p} value={p}>
                        {proficiencyLabel(p)}
                      </option>
                    ))}
                  </Select>
                )}
              </li>
            );
          })}
        </ul>
      </div>

      <StepError message={error} />
      <StepFooter step={step} totalSteps={totalSteps} onBack={onBack} status={<SaveStatus state={state} />} />
    </form>
  );
}
