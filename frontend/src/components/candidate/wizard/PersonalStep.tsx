"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { Field, Select, TextArea, TextInput } from "@/components/ui/Field";
import type { CandidateProfile, Lookups } from "@/lib/types";
import { patchProfile, useDistricts, useStates } from "@/lib/candidate";
import { SaveStatus, StepError, useSaver } from "./shared";
import { StepFooter } from "./StepFooter";

const schema = z.object({
  full_name: z.string().trim().min(1, "Please enter your full name.").max(191),
  dob: z.string().optional().or(z.literal("")),
  gender: z.enum(["male", "female", "other"]).optional().or(z.literal("")),
  phone: z.string().trim().max(30).optional().or(z.literal("")),
  whatsapp: z.string().trim().max(30).optional().or(z.literal("")),
  address: z.string().trim().max(500).optional().or(z.literal("")),
  country_id: z.string().optional().or(z.literal("")),
  state_id: z.string().optional().or(z.literal("")),
  district_id: z.string().optional().or(z.literal("")),
  city: z.string().trim().max(120).optional().or(z.literal("")),
  pincode: z.string().trim().max(20).optional().or(z.literal("")),
});

type Values = z.infer<typeof schema>;

const toNum = (v?: string): number | null => (v && v !== "" ? Number(v) : null);
const orNull = (v?: string): string | null => (v && v !== "" ? v : null);

export function PersonalStep({
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
  const { state, error, run } = useSaver();
  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      full_name: profile.full_name ?? "",
      dob: profile.dob ?? "",
      gender: profile.gender ?? "",
      phone: profile.phone ?? "",
      whatsapp: profile.whatsapp ?? "",
      address: profile.address ?? "",
      country_id: profile.country_id ? String(profile.country_id) : "",
      state_id: profile.state_id ? String(profile.state_id) : "",
      district_id: profile.district_id ? String(profile.district_id) : "",
      city: profile.city ?? "",
      pincode: profile.pincode ?? "",
    },
  });

  const countryValue = useWatch({ control, name: "country_id" });
  const stateValue = useWatch({ control, name: "state_id" });
  const countryId = toNum(countryValue);
  const selectedCountry = lookups.countries.find((c) => c.id === countryId) ?? null;
  const hasStates = selectedCountry?.has_states ?? false;

  const stateId = toNum(stateValue);
  const { states } = useStates(hasStates ? countryId : null);
  const { districts } = useDistricts(hasStates && stateId ? stateId : null);

  const onSubmit = handleSubmit(async (values) => {
    const ok = await run(() =>
      patchProfile({
        full_name: values.full_name.trim(),
        dob: orNull(values.dob),
        gender: orNull(values.gender),
        phone: orNull(values.phone),
        whatsapp: orNull(values.whatsapp),
        address: orNull(values.address),
        country_id: toNum(values.country_id),
        state_id: hasStates ? toNum(values.state_id) : null,
        district_id: hasStates ? toNum(values.district_id) : null,
        city: hasStates ? null : orNull(values.city),
        pincode: orNull(values.pincode),
        wizard_step: Math.max(profile.wizard_step, step + 1),
      }),
    );
    if (ok) onNext();
  });

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <Field label="Full name (as on passport)" htmlFor="full_name" error={errors.full_name?.message}>
        <TextInput id="full_name" autoComplete="name" {...register("full_name")} />
      </Field>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Date of birth" htmlFor="dob" error={errors.dob?.message}>
          <TextInput id="dob" type="date" {...register("dob")} />
        </Field>
        <Field label="Gender" htmlFor="gender" error={errors.gender?.message}>
          <Select id="gender" {...register("gender")}>
            <option value="">Select…</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </Select>
        </Field>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Phone" htmlFor="phone" error={errors.phone?.message}>
          <TextInput id="phone" type="tel" inputMode="tel" autoComplete="tel" {...register("phone")} />
        </Field>
        <Field label="WhatsApp number" htmlFor="whatsapp" error={errors.whatsapp?.message}>
          <TextInput id="whatsapp" type="tel" inputMode="tel" {...register("whatsapp")} />
        </Field>
      </div>

      <Field label="Address" htmlFor="address" error={errors.address?.message}>
        <TextArea id="address" rows={2} {...register("address")} />
      </Field>

      <Field label="Country" htmlFor="country_id" error={errors.country_id?.message}>
        <Select id="country_id" {...register("country_id")}>
          <option value="">Select country…</option>
          {lookups.countries.map((country) => (
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

      <Field label="PIN / postal code" htmlFor="pincode" error={errors.pincode?.message}>
        <TextInput id="pincode" inputMode="numeric" {...register("pincode")} />
      </Field>

      <StepError message={error} />
      <StepFooter step={step} totalSteps={totalSteps} onBack={onBack} status={<SaveStatus state={state} />} />
    </form>
  );
}
