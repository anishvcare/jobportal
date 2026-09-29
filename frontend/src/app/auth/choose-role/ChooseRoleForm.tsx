"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { PageLoader } from "@/components/ui/Spinner";
import { api } from "@/lib/api";
import { homeFor, readRoleIntent } from "@/lib/auth";
import { errorMessage } from "@/lib/errors";
import type { User } from "@/lib/types";

const schema = z.object({
  role: z.enum(["candidate", "employer"], { message: "Please choose one option." }),
});

type FormValues = z.infer<typeof schema>;

const OPTIONS = [
  {
    value: "candidate",
    title: "I'm looking for a job",
    description: "Create your profile, upload your documents and apply to jobs.",
  },
  {
    value: "employer",
    title: "I'm hiring",
    description: "Register your company and post jobs after a quick approval by our team.",
  },
] as const;

export function ChooseRoleForm() {
  const { user, isLoading, setUser } = useAuth();
  const router = useRouter();
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setValue,
    control,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  useEffect(() => {
    const intent = readRoleIntent();
    if (intent) setValue("role", intent);
  }, [setValue]);

  useEffect(() => {
    if (isLoading) return;
    if (!user) router.replace("/auth/login");
    else if (!user.needs_onboarding) router.replace(homeFor(user));
  }, [isLoading, user, router]);

  const selected = useWatch({ control, name: "role" });

  if (isLoading || !user || !user.needs_onboarding) return <PageLoader />;

  const onSubmit = handleSubmit(async (values) => {
    setServerError(null);
    try {
      const { data } = await api.post<{ data: User }>("/onboarding/role", values);
      await setUser(data.data);
      router.replace(homeFor(data.data));
    } catch (error) {
      setServerError(errorMessage(error));
    }
  });

  return (
    <Card>
      <h1 className="text-2xl font-bold">Welcome, {user.name.split(" ")[0]}!</h1>
      <p className="mt-2 text-sm text-slate-600">How will you use Nexus Flow? You can&apos;t change this later.</p>

      <form onSubmit={onSubmit} className="mt-6 space-y-3" noValidate>
        <fieldset className="space-y-3">
          <legend className="sr-only">Account type</legend>
          {OPTIONS.map((option) => (
            <label
              key={option.value}
              className={`flex cursor-pointer gap-3 rounded-lg border p-4 ${
                selected === option.value ? "border-brand-600 bg-brand-50 ring-1 ring-brand-600" : "border-slate-300 bg-white"
              }`}
            >
              <input type="radio" value={option.value} {...register("role")} className="mt-1 h-4 w-4 accent-brand-600" />
              <span>
                <span className="block font-semibold">{option.title}</span>
                <span className="block text-sm text-slate-600">{option.description}</span>
              </span>
            </label>
          ))}
        </fieldset>

        {errors.role && <p className="text-sm text-red-700">{errors.role.message}</p>}
        {serverError && <Alert tone="error">{serverError}</Alert>}

        <Button type="submit" loading={isSubmitting} className="w-full">
          Continue
        </Button>
      </form>
    </Card>
  );
}
