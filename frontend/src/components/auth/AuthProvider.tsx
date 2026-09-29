"use client";

import { createContext, useCallback, useContext, useMemo, type ReactNode } from "react";
import useSWR from "swr";
import { api } from "@/lib/api";
import { httpStatus } from "@/lib/errors";
import type { User } from "@/lib/types";

interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  /** Set when /me failed for a reason other than "not signed in" (e.g. offline). */
  error: unknown;
  setUser: (user: User | null) => Promise<void>;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

async function fetchMe(): Promise<User | null> {
  try {
    const response = await api.get<{ data: User }>("/me");
    return response.data.data;
  } catch (error) {
    if (httpStatus(error) === 401) return null;
    throw error;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const { data, error, isLoading, mutate } = useSWR("auth/me", fetchMe, {
    revalidateOnFocus: true,
    shouldRetryOnError: false,
    dedupingInterval: 10_000,
  });

  const setUser = useCallback(async (user: User | null) => {
    await mutate(user, { revalidate: false });
  }, [mutate]);

  const refresh = useCallback(async () => {
    await mutate();
  }, [mutate]);

  const logout = useCallback(async () => {
    try {
      await api.post("/logout");
    } finally {
      await mutate(null, { revalidate: false });
    }
  }, [mutate]);

  const value = useMemo<AuthContextValue>(
    () => ({ user: data ?? null, isLoading, error, setUser, refresh, logout }),
    [data, isLoading, error, setUser, refresh, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error("useAuth must be used inside <AuthProvider>");
  return context;
}
