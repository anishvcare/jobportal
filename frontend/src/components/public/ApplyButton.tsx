"use client";

import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Field, TextArea } from "@/components/ui/Field";
import { useAuth } from "@/components/auth/AuthProvider";
import { api } from "@/lib/api";
import { rememberRoleIntent } from "@/lib/auth";
import { errorMessage, httpStatus } from "@/lib/errors";

type ApplyState = "idle" | "applied" | "already" | "closed" | "error";

/**
 * Login-gated Apply action for a public job detail page.
 *
 * - Guests are routed to login (redirect back to this job as a candidate).
 * - Signed-in candidates POST to the apply endpoint and see success /
 *   already-applied (409) / closed (422) feedback.
 * - Employers and admins see a non-apply info state.
 */
export function ApplyButton({ slug, title }: { slug: string; title: string }) {
  const { user, isLoading } = useAuth();
  const [state, setState] = useState<ApplyState>("idle");
  const [message, setMessage] = useState<string>("");
  const [coverNote, setCoverNote] = useState<string>("");
  const [submitting, setSubmitting] = useState(false);

  if (isLoading) {
    return (
      <Button variant="primary" loading disabled>
        Loading…
      </Button>
    );
  }

  // Guest: send to login and come back to this job as a candidate.
  if (!user) {
    const loginHref = `/auth/login?redirect=${encodeURIComponent(`/jobs/${slug}`)}&as=candidate`;
    return (
      <div className="space-y-2">
        <ButtonLink
          href={loginHref}
          variant="primary"
          onClick={() => rememberRoleIntent("candidate")}
        >
          Sign in to apply
        </ButtonLink>
        <p className="text-xs text-slate-500">You need a candidate account to apply for this job.</p>
      </div>
    );
  }

  // Employers / admins cannot apply.
  if (user.role !== "candidate") {
    return (
      <Alert tone="info">
        You are signed in as {user.role === "employer" ? "an employer" : "an admin"}. Only candidate accounts can apply
        to jobs.
      </Alert>
    );
  }

  if (state === "applied") {
    return <Alert tone="success">Your application for {title} has been submitted.</Alert>;
  }
  if (state === "already") {
    return <Alert tone="info">You have already applied to this job.</Alert>;
  }
  if (state === "closed") {
    return <Alert tone="warning">This job is no longer accepting applications.</Alert>;
  }

  async function submit() {
    setSubmitting(true);
    setState("idle");
    setMessage("");
    try {
      const note = coverNote.trim();
      await api.post(`/candidate/jobs/${encodeURIComponent(slug)}/apply`, note ? { cover_note: note } : {});
      setState("applied");
    } catch (error) {
      const status = httpStatus(error);
      if (status === 409) {
        setState("already");
      } else if (status === 422) {
        setState("closed");
      } else {
        setState("error");
        setMessage(errorMessage(error));
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="space-y-3">
      {state === "error" && <Alert tone="error">{message}</Alert>}
      <Field label="Cover note (optional)" htmlFor="cover_note">
        <TextArea
          id="cover_note"
          rows={4}
          maxLength={2000}
          value={coverNote}
          onChange={(e) => setCoverNote(e.target.value)}
          placeholder="Tell the employer why you're a good fit."
        />
      </Field>
      <Button variant="primary" loading={submitting} onClick={submit}>
        Apply now
      </Button>
    </div>
  );
}
