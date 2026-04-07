# Authorization Model

## Scope Rules

- `ClientScope` applies to client users and filters project records by `projects.client_id = authenticated user's Client.id`.
- `TeamMemberScope` applies to authenticated non-client users who are not `admin`/`super-admin` and do not have `view_all_projects`.
- `TeamMemberScope` currently filters `Project` queries to records where the authenticated user exists in `project.members`.

## Project Access

- Clients can view only their own projects and can approve UI only for their own projects.
- `admin` and `super-admin` can view and update all projects.
- Users with `view_all_projects` can view all projects.
- Users with `manage_all_projects` can update all projects.
- Other employees can view and update only projects where they are members.
- Only `admin` and `super-admin` can delete projects.

## Invoice Access

- Clients can view and pay only invoices whose project belongs to their `Client` profile.
- Non-client users can create, update, and delete invoices.
- `admin`, `super-admin`, and `employee` currently can view invoices through `InvoicePolicy`.
- `admin` and `super-admin` can pay invoices on behalf of a client when the invoice is payable.

## Current Divergence

- Project visibility for employees is membership-scoped through `TeamMemberScope` and `ProjectPolicy`.
- Invoice visibility is broader: `InvoicePolicy` currently allows all users with the `employee` role to view invoices, even if they are not project members.
- This divergence is intentional until invoice access is explicitly tightened. Tests should reflect this documented behavior rather than assume project-style membership scoping for invoices.
