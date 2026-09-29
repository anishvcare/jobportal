import type { Metadata } from "next";
import { ChooseRoleForm } from "./ChooseRoleForm";

export const metadata: Metadata = { title: "Welcome" };

export default function ChooseRolePage() {
  return <ChooseRoleForm />;
}
