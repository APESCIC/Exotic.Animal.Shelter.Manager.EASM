# What EASM does

Exotic Animal Shelter Manager (EASM) is self-hosted software for one exotic-animal shelter per install. It is a new application, not a fork of Animal Shelter Manager or sheltermanager.com. Species stay free text; there is no dog and cat vocabulary. Defaults are UK-first: locale `en_GB`, timezone `Europe/London`, dates shown as `dd/mm/yyyy`.

To install it, see [install.md](install.md). For the order features arrive in, see [roadmap.md](roadmap.md).

## Sign-in and roles

Sign in at `/login` with the admin email and password. Roles are admin, staff, volunteer and readonly. Only admins can open `/admin` and change organisation settings (name, locale, timezone). Successful logins are written to `login_events`. There is no external SSO; sign-in uses EASM's own admin and staff accounts.

## Animals and care

- **Animals:** admin and staff manage animal records at `/animals` (free-text species, primary photo, enclosure, CITES and DWA details, find and filter, a shelter view by location). Public photos use `public/storage` when a symlink is available, or the `/storage/{path}` route on hosts that block `symlink()`.
  - Colour, breed/type, entry reason, death reason and flags use **lookup lists** (select / multi-select). Species and location stay free text.
  - When lookups first ship, existing free-text values are copied into the lists so records keep their colour, breed, reasons and flags.
- **Movements:** admin and staff record movements on an animal (intake, hold, quarantine, foster, trial adoption, adoption, reclaim, transfer, deceased), with history on the animal record. A deceased movement updates the animal's deceased date and death reason. Optional contact links use people from `/people`.
- **Medical care:** admin and staff record vaccinations, tests, treatments, diets and daily observations from the animal record.
- **Media:** admin and staff upload extra photos and PDFs to an animal (Media section), separate from the primary photo.
- **Lookups:** admins manage reusable lists at `/admin/lookups` (colour, breed/type, entry reason, death reason, flags, and any lists used by custom fields). Values are retired, not deleted. Creating, updating and retiring a value is audited. Only admins can open or change lookups; staff, volunteers and read-only users get 403.
- **Custom fields:** admins define them under `/admin/custom-fields` for **animals** or **people** (text, number, date, yes/no, lookup, multi-lookup). Options include mandatory, tooltip, display order, show-on-new-record, species filter (animals only), and retire-instead-of-delete. Staff fill values on create/edit; animal search also matches animal field values. This enhances the original custom-fields work rather than replacing it.
- **Diary:** admin and staff create and complete diary tasks from an animal record or the shared inbox at `/diary`.
- **Stock:** staff track food, medical and other supplies at `/stock`. Each item has a unit, quantity, reorder level and location. Every change (delivery, use, waste, stocktake count) is kept as history. Adding a medical record can take the stock used off at the same time. Items at or below their reorder level raise a **Low stock** alert on the dashboard. Volunteers can record deliveries and usage; staff manage the item list.
- **Reminders:** equipment replacements and DWA renewals need no cron job; they are raised when anyone opens the dashboard or diary. `php artisan easm:reminders` does the same from the command line if you prefer a scheduled task.

## Animal control

- **Cases:** admin and staff log municipal-style callouts at `/animal-control` (reference such as `AC-2026-0001`, reported time, location, free-text species, summary, optional reporter and linked animal). Signed-in users can view the list and case pages; volunteers and read-only users cannot create or change cases.
- **Dispatch and close:** staff assign a dispatcher (admin or staff user) so the case becomes **Dispatched**, then close it with optional notes. Bite records, citations, trap loan and pet licensing are not in this MVP.

## Compliance

- **CITES register:** admin, staff and read-only users keep a CITES register at `/compliance/cites`.
  - Staff set each animal's CITES Annex (A, B, C, D or not listed) on the animal record.
  - Staff add certificates (Article 10, import or export permits), with an optional scan or PDF, on the animal's Legal tab. Certificate files are stored privately under `storage/app/private/cites`, and only signed-in admin, staff and read-only users can download them.
  - The register lists animals in care (or, optionally, everyone who has left too) and downloads as CSV.
  - Annex A animals in care with no Article 10 certificate are flagged **No Article 10: needed before any sale or fee-paid rehoming** and raise a **No Article 10 (Annex A)** alert on the dashboard. APES treats fee-paid rehoming as needing Article 10 (shelter decision on published guidance; not APHA-signed advice).
  - Staff can tick **Captive-bred and exempt under Annex X** on an Annex A animal to clear the flag. An export or re-export permit issued in the last 6 months also counts as cover, and the register shows the date it runs out.
  - EASM does not look up Annex listings or decide when a certificate is needed. It is a record-keeping aid, not legal advice, so check current APHA guidance.
- **Dangerous Wild Animals Act licences:** these live at `/compliance/dwa`.
  - Staff record each licence (number, issuing council, species covered, conditions, issue and expiry dates), link it to the animals it covers, and record council inspections (date, inspector, outcome, notes). Linked licences also show on each animal's Legal tab.
  - A new licence's expiry defaults to the same date two years after issue (DWA Act 1976 s.1(3)); change it if your licence differs.
  - Sixty days before a licence expires, EASM adds a **Renew DWA licence** task to the diary. Admins can set this to anywhere from 7 to 180 days under Settings, to fit their council's renewal window. The task is added once per expiry date, and changing the expiry date after renewal closes the old task.
  - The dashboard shows **DWA licences to renew** for licences expired or expiring within that window.
  - Every signed-in user can view licences; admin and staff edit them. Confirm conditions, renewal and inspection arrangements with the issuing council: this is not legal advice.

## People, adoption and applications

- **People:** admin and staff manage people and contacts at `/people` (categories, banned and homechecked flags, find and filter). People custom fields defined for the person entity appear on create/edit and on the person record (species filter does not apply).
- **Waiting list:** admin and staff keep a waiting list at `/waiting-list` of people who want to surrender an animal or adopt one.
  - Each entry has an urgency (urgent, high, normal, low), a species (free text), and a linked contact or free-text contact details.
  - The list shows the most urgent first, then the longest waiting.
  - Entries are closed as placed or removed, and can be reopened.
- **Public adoption pages:** guests can browse animals staff have marked **Listed for public adoption** at `/adopt` (detail at `/adopt/{id}`, an embeddable list at `/adopt/embed`). A read-only JSON API is at `GET /api/v1/adoptable` and `GET /api/v1/adoptable/{id}` (public-safe fields only; no authentication).
- **Partner feed:** admins can share the same adoptable list with partner sites under **Administration → Partner feed**: a one-click CSV download, and an optional live feed at `/feeds/adoptable.{csv,json,rss}?token=…` that partners poll on their own schedule. See [publishing.md](publishing.md).
- **Applications:** guests can submit fixed online forms at `/apply` (adopter, volunteer, foster), or about one animal at `/adopt/{id}/apply`. Staff review the queue at `/applications`; accepting an application creates a Person contact only, not an Animal.

## Documents and finance

- **Documents:** admin and staff generate documents (adoption contract, invoice, enclosure card) from an animal's Documents tab or a person's record, collect a mouse or touch signature, and download them as PDF. Signed documents are archived as PDF under `storage/app/private/documents` and cannot be changed. Admins edit the templates under `/admin/document-templates`; the default contract wording is a starting point to check with your own legal adviser.
- **Finance:** admin and staff record donations, adoption fees and sponsorship payments in the ledger at `/finance`, against a person or animal or both, with totals by type and date.
  - Animals show their current sponsors on the animal record; people show payments and sponsorships on their profile.
  - Read-only users can view the ledger; volunteers cannot see finance. There is no card or online payment processing.

## Reports, mobile and rota

- **Reports:** admin, staff and read-only users run reports at `/reports`: occupancy on a chosen date (by species and enclosure), intake and outcome counts over a date range, and an animal timeline of stays in care. Each downloads as CSV.
- **Mobile tasks:** staff and volunteers on a phone use **Mobile tasks** at `/m`. It shows today's shifts, tasks and medication due (with large Done and Given buttons), a quick log entry or camera photo per animal, documents to sign with a finger, and enclosure pages for environment readings.
  - Print enclosure QR labels from `/enclosures/qr-labels`; scanning one opens that enclosure's page.
  - On HTTPS the app installs to the home screen and keeps visited mobile pages available offline.
  - Changes made without signal are saved on the phone and sent once it is back online, and each is applied only once.
- **Rota:** the staff and volunteer rota is at `/rota`. Admins and staff plan shifts; everyone sees the week and **My shifts**.

## Admin: lookups and custom fields (Herd checks)

Local only (`https://easm.test` / live reload on `:5176`). Sign in as `admin@easm.test` with the fake demo password in [local/demo-data.md](local/demo-data.md). Do not use Laragon.

1. **Manage a list:** Administration → Lookups → open Colour → Add value → save. Confirm it appears Active.
2. **Retire a value:** Edit that value, untick Active, save. Confirm it shows as Retired and no longer appears on a **new** animal form's colour list.
3. **Migrated animal value:** Open an existing demo animal that still has a colour or flags from before lookups. Confirm the value still shows on the record and on edit (including retired values already on the record).
4. **Mandatory custom field:** Administration → Custom fields → add an animal text field with Mandatory on → edit an animal and leave it empty → save should show an error next to that field.
5. **Species filter:** Add an animal field with species filter `Bearded dragon` → it appears on a bearded dragon edit form and not on a corn snake.
6. **People custom field:** Add a person-entity field → it appears on People create/edit and show, and not on animal forms.

Permissions: only **admin** manages lookups and custom-field definitions. **Admin and staff** fill values on animals and people. Volunteers and read-only users cannot manage definitions.

## What EASM does not include

- External SSO or a shared sign-in with other APES apps; EASM uses its own local accounts.
- Integrations built for specific partner sites, microchip registry updates and automatic social posting. The generic partner feed covers publishing; see [publishing.md](publishing.md).
- Public lost and found intake forms. Staff lost and found matching already ships.
