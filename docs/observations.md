# Daily observations

Structured daily observations extend the animal **Log** tab (journal work from issue #16). Admins configure observation types; admin, staff and volunteers record values on single-animal forms or in bulk. Read-only users can view the log but cannot add or change entries.

## Set-up (admin)

1. Sign in as admin at `https://easm.test` (Laravel Herd; see [local/demo-data.md](local/demo-data.md) for the demo password).
2. Open **Administration → Observation types**.
3. Add types with either **Value list** (linked to a lookup list under **Administration → Lookups**) or **Free text**.
4. Optional **Species filter**: one species per line, matched case-insensitively against the animal’s free-text species. Leave blank to show the type for every species.
5. Retire a type by unticking **Active**; past log entries keep their type and value.

The installer and `DatabaseSeeder` include starter reptile and bird types when lookup lists are seeded.

## Daily use

- **Single animal:** open the animal → **Log** → **Add observation**. Choose a type (or legacy free-text only), enter the value and optional notes, and set the date.
- **Bulk:** **Log** → **Bulk entry**, or **Enclosures** → open an enclosure → **Bulk daily observations**. Pick an enclosure or a location, tick animals to include, fill values in the grid, and save. Only cells with values create observations.
- **Mobile:** **Mobile tasks** → animal → add a log entry (legacy note or type when configured).

## Edit and delete (24-hour rule)

- The author may edit or delete their own entry within **24 hours** of when it was saved.
- **Admins** may edit or delete any entry at any time.
- Other roles receive 403 on edit/delete outside those rules.
- Creates, updates and deletes are written to **audit_logs** (visible on the animal **Audit** tab when used).

## Permissions

| Role | View log | Record | Edit own (24h) | Edit any |
| --- | --- | --- | --- | --- |
| Admin | Yes | Yes | Yes | Yes |
| Staff | Yes | Yes | Yes | No |
| Volunteer | Yes | Yes | Yes | No |
| Read only | Yes | No | No | No |

## Log tab filters

Filter by observation type to see entries for that type from the **last 30 days** only. Clear the filter to see the full history.

## Herd test steps

1. Run migrations: `herd php artisan migrate` from the repo root (PHP 8.3 on PATH per [AGENTS.md](../AGENTS.md)).
2. As admin, open **Observation types** and confirm starter types exist after seeding.
3. As staff, open an animal **Log** tab, add a value-list observation, and confirm it appears with type and value.
4. As volunteer, add another observation; confirm success.
5. As read-only, open **Add observation** and confirm 403.
6. Use **Bulk entry** for an enclosure with three animals ticked and one type filled each; confirm three new rows on the log.
7. Edit your own entry within 24 hours; travel time or adjust `created_at` in a test database to confirm edit is blocked after 24 hours for non-admins.