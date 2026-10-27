# Publishing adoptable animals to partner sites

EASM shares the animals staff mark **Listed for public adoption** with partner websites, rehoming directories and social media tools. There are two ways to do it:

- **Download CSV** (Administration → Partner feed). Use this for partners that accept a spreadsheet upload.
- **Live partner feed.** A private web address that returns the current list as CSV, JSON or RSS. Partners fetch it on their own schedule (for example hourly), so it is always up to date. EASM needs no cron job and sends nothing on its own.

An animal is included when it is listed for public adoption, not deceased, and not a non-shelter record. These are the same rules as the public `/adopt` pages.

## Switching the feed on

1. Sign in as an admin and open **Administration → Partner feed**.
2. Tick **Switch on the live partner feed** and save.
3. Copy the CSV, JSON or RSS address and send it to the partner.

The addresses contain a secret token, so share them only with partners. If an address leaks, choose **Create new feed address**. Old addresses stop working straight away, and every partner then needs the new one. Switching the feed off makes every address return `404 Not Found`.

## Addresses

```
GET /feeds/adoptable.csv?token=…
GET /feeds/adoptable.json?token=…
GET /feeds/adoptable.rss?token=…
```

Responses are not cached (`Cache-Control: no-store`), carry `X-Robots-Tag: noindex`, and are limited to 60 requests a minute per client.

## Fields

Every format carries the same public-safe fields. CITES, DWA, location, enclosure, medical and contact details are never included.

| Field | Notes |
|-------|-------|
| `id` | EASM animal ID, stable for the life of the record |
| `name` | |
| `species` | Free text, as recorded |
| `breed_type` | Morph, subspecies or type, if recorded |
| `sex` | `male`, `female`, `unknown` or `mixed` |
| `age_years` | Whole years, if recorded |
| `date_of_birth` | `YYYY-MM-DD`, if recorded |
| `colour` | |
| `bonded_animals` | Free text, for animals that must be rehomed together |
| `photo_url` | Absolute URL of the primary photo, if any |
| `url` | Public adoption page for the animal on this site |
| `updated_at` | ISO 8601 time the record last changed |

- **CSV:** UTF-8 with a header row, in the column order above. Cells that start with `=`, `+`, `-` or `@` are prefixed with `'` so spreadsheets do not run them as formulas.
- **JSON:** `{ "organisation", "generated_at", "count", "data": [ … ] }`.
- **RSS 2.0:** one `<item>` per animal, linking to its public page, with the primary photo as an `<enclosure>`.

The public JSON API at `/api/v1/adoptable` returns the same fields, paginated, with no token.

## Not included

- Integrations built for specific sites (posting directly into a partner's own API). Partners poll the feed or import the CSV instead.
- Microchip registry updates.
- Automatic social media posting. RSS-to-social tools can read the RSS feed.
