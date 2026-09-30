"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useRef, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { EmployerNav } from "@/components/employer/EmployerNav";
import { EmployerStatusBadge } from "@/components/employer/StatusBadges";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, PageHeader } from "@/components/ui/Card";
import { Field, Select, TextInput } from "@/components/ui/Field";
import { PageLoader } from "@/components/ui/Spinner";
import {
  deleteLogo,
  updateEmployerProfile,
  uploadLogo,
  useDistricts,
  useEmployerProfile,
  useLookups,
  useStates,
} from "@/lib/employer";
import { errorMessage } from "@/lib/errors";

const schema = z.object({
  company_name: z.string().trim().min(1, "Please enter your company name.").max(191),
  contact_person: z.string().trim().max(191).optional().or(z.literal("")),
  phone: z.string().trim().max(30).optional().or(z.literal("")),
  website: z.string().trim().url("Enter a valid URL (including https://).").max(255).optional().or(z.literal("")),
  country_id: z.string().optional().or(z.literal("")),
  state_id: z.string().optional().or(z.literal("")),
  district_id: z.string().optional().or(z.literal("")),
  city: z.string().trim().max(120).optional().or(z.literal("")),
});

type Values = z.infer<typeof schema>;

const toNum = (v?: string): number | null => (v && v !== "" ? Number(v) : null);
const orNull = (v?: string): string | null => (v && v !== "" ? v : null);

export default function EmployerProfilePage() {
  const { profile, isLoading, error } = useEmployerProfile();

  if (error) return <><EmployerNav /><Alert tone="error">{errorMessage(error)}</Alert></>;
  if (isLoading || !profile) {
    return <><EmployerNav /><PageLoader label="Loading company profile…" /></>;
  }

  return (
    <>
      <EmployerNav />
      <PageHeader
        title="Company profile"
        description="These details appear on your job postings."
        actions={<EmployerStatusBadge status={profile.status} />}
      />
      <ProfileForm key={profile.id} />
    </>
  );
}

function ProfileForm() {
  const { profile, mutate } = useEmployerProfile();
  const { lookups } = useLookups();
  const [saved, setSaved] = useState(false);
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
      company_name: profile?.company_name ?? "",
      contact_person: profile?.contact_person ?? "",
      phone: profile?.phone ?? "",
      website: profile?.website ?? "",
      country_id: profile?.country_id ? String(profile.country_id) : "",
      state_id: profile?.state_id ? String(profile.state_id) : "",
      district_id: profile?.district_id ? String(profile.district_id) : "",
      city: profile?.city ?? "",
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

  const onSubmit = handleSubmit(async (values) => {
    setSaving(true);
    setSaved(false);
    setSaveError(null);
    try {
      await updateEmployerProfile({
        company_name: values.company_name.trim(),
        contact_person: orNull(values.contact_person),
        phone: orNull(values.phone),
        website: orNull(values.website),
        country_id: toNum(values.country_id),
        state_id: hasStates ? toNum(values.state_id) : null,
        district_id: hasStates ? toNum(values.district_id) : null,
        city: hasStates ? null : orNull(values.city),
      });
      await mutate();
      setSaved(true);
    } catch (err) {
      setSaveError(errorMessage(err));
    } finally {
      setSaving(false);
    }
  });

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_18rem]">
      <Card className="lg:order-1">
        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <Field label="Company name" htmlFor="company_name" error={errors.company_name?.message}>
            <TextInput id="company_name" {...register("company_name")} />
          </Field>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Contact person" htmlFor="contact_person" error={errors.contact_person?.message}>
              <TextInput id="contact_person" {...register("contact_person")} />
            </Field>
            <Field label="Phone" htmlFor="phone" error={errors.phone?.message}>
              <TextInput id="phone" type="tel" inputMode="tel" {...register("phone")} />
            </Field>
          </div>

          <Field label="Website" htmlFor="website" error={errors.website?.message} hint="Include https://">
            <TextInput id="website" type="url" placeholder="https://example.com" {...register("website")} />
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

          {saveError && <Alert tone="error">{saveError}</Alert>}
          {saved && <Alert tone="success">Company profile saved.</Alert>}

          <Button type="submit" loading={saving}>
            Save profile
          </Button>
        </form>
      </Card>

      <aside className="lg:order-2">
        <LogoCard />
      </aside>
    </div>
  );
}

function LogoCard() {
  const { profile, mutate } = useEmployerProfile();
  const inputRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [logoError, setLogoError] = useState<string | null>(null);

  async function onFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setBusy(true);
    setLogoError(null);
    try {
      await uploadLogo(file);
      await mutate();
    } catch (err) {
      setLogoError(errorMessage(err));
    } finally {
      setBusy(false);
      if (inputRef.current) inputRef.current.value = "";
    }
  }

  async function onRemove() {
    setBusy(true);
    setLogoError(null);
    try {
      await deleteLogo();
      await mutate();
    } catch (err) {
      setLogoError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <h2 className="font-semibold">Company logo</h2>
      <div className="mt-3 flex items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
        {profile?.logo_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- remote logo, not a static asset
          <img src={profile.logo_url} alt="Company logo" className="h-24 w-24 rounded object-contain" />
        ) : (
          <span className="text-sm text-slate-400">No logo uploaded</span>
        )}
      </div>

      {logoError && <div className="mt-3"><Alert tone="error">{logoError}</Alert></div>}

      <div className="mt-4 flex flex-col gap-2">
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          className="hidden"
          onChange={onFile}
          aria-label="Upload company logo"
        />
        <Button type="button" variant="secondary" loading={busy} onClick={() => inputRef.current?.click()}>
          {profile?.logo_url ? "Replace logo" : "Upload logo"}
        </Button>
        {profile?.logo_url && (
          <Button type="button" variant="ghost" disabled={busy} onClick={onRemove}>
            Remove logo
          </Button>
        )}
      </div>
    </Card>
  );
}
