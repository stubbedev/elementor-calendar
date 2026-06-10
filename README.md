# Timeslot Booking

Custom WordPress + Elementor plugin. You define any number of **session types**,
each with its own length, weekly availability, client email flow and optional
**Google Meet** link. Visitors **pick a session type** (when more than one is
enabled), **pick a day** on a month calendar (days with free times are
highlighted; days without are shown but not clickable), **then pick a timeslot**
(animated reveal), **then** fill the contact form on a separate step with a back
button. Availability is generated per type from per-weekday opening hours (slot
length, start offset, and gap between slots all configurable), with public
holidays (any country, via date.nager.at), weekends, and individual slots/days
auto-blocked. **Overbooking is prevented across session types** — bookings are
compared as time ranges, so a 60-minute booking blocks the 30-minute slots that
overlap it. Bookings appear in their own top-level **Bookings** menu as a native,
sortable, searchable list.

The UI is fully internationalized (English source, ships with Danish; follows the
WordPress locale and integrates with WPML/Polylang), and the widget exposes a deep
set of Elementor **Style** controls that default to the active theme's colors.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Elementor (free) — the booking widget needs it. Without Elementor the plugin
  loads but shows an admin notice and renders no widget.

## Install

1. Copy the `timeslot-booking/` folder into `wp-content/plugins/`.
2. Activate **Timeslot Booking** in *Plugins*. Activation creates two tables
   (`{prefix}_tsb_bookings`, `{prefix}_tsb_blocked`).
3. Configure under **Bookinger → Indstillinger** in wp-admin (see Tabs).
4. Edit a page in Elementor → drag the **Timeslot Booking** widget onto the page.

## Admin

The admin is a **React single-page app** on WordPress' bundled React
(`wp.element` + `@wordpress/components`) — no build step, no extra runtime — talking
to a REST API (`TSB_REST`, namespace `tsb/v1`, `manage_options` + nonce). The
**Bookings** top-level menu hosts it:

- **All bookings** — sortable/searchable/paginated table; cancel, restore, delete;
  **reschedule with live availability** (below); CSV export.
- **Settings** — tabbed forms (below) saved over REST.

**Reschedule shows real availability:** picking a new date fetches that day's open
slots via `GET tsb/v1/availability` (from `TSB_Availability::day_grid()`) and renders
them as buttons — booked/blocked times are disabled, and the move is rejected
server-side (409) if the target is taken. No more blind time entry.

REST: `GET/POST /settings`, `GET/POST /types`, `GET /bookings`,
`POST|DELETE /bookings/{id}` (`op=move|cancel|restore`),
`GET /availability?date=&exclude=&type=`, `GET /month?year=&month=&type=`,
`GET/POST /blocks`, `DELETE /blocks/{id}`, `GET /google`, `POST /google/disconnect`.
The Google OAuth redirect lands on `admin-post.php?action=tsb_google_callback`. CSV
export stays a server-side `admin-post` download. Admin SPA strings translate via
`wp.i18n` + `wp_set_script_translations` (Danish JSON in `languages/`).

| Tab | What it controls |
|-----|------------------|
| **Session types** | Add/duplicate/reorder/delete types. Per type: name + description, on/off, **Availability** (base hours, per-weekday open/closed, slot length/offset/gap, days ahead, lead time, holidays), **Emails** (confirmation, moved, cancelled, reminder — MJML editor + `.ics` settings + reminder window), **Video & invite** (Google Meet toggle, `.ics` title/location) |
| **Form** | Dynamic, ordered form fields (name + email always shown); **GDPR consent** checkbox (text + optional link, server-enforced). Shared by all types |
| **Notifications** | The internal admin notification email (shared) |
| **Google** | OAuth client ID/secret + calendar ID; connect/disconnect the Google account used for Meet links |
| **Spam protection** | none / honeypot / reCAPTCHA v2 / reCAPTCHA v3 (score) / hCaptcha + keys |
| **Blocks** | Remove individual times or whole days from availability (global) |

Availability and the customer-facing email flow are **per session type**; form
fields, consent, spam protection, the admin notification and the Google account
are **global**.

### Form behaviour

- **Inline validation** — required/email checks run client-side with a message
  under each field; the server re-validates everything as the source of truth.
- **Submit feedback** — spinner + "Sending…"; on success the form is replaced by a
  summary card (date/time + booking reference) with a **Book another** button.
- **Accessibility** — `aria-live`/`role` on status + errors, `aria-required`, focus
  moves to the first field on step 3 and to the first invalid field on error;
  browser autocomplete hints on name/email/phone.
- **Anti-spam** — honeypot **plus** a signed time-trap (rejects sub-3s/scripted
  posts that skipped loading the slots). A cached page that hits an expired nonce
  auto-refreshes its token and retries once.

### Widget styling (Elementor)

The widget exposes Elementor **Style** controls (Calendar, Form, Buttons):
accent colour, calendar cell typography/colour/background/radius, form label and
input typography/colour/background/border/radius, and submit/back button
typography, colours, hover, and radius — all themeable per instance.

## Languages & translation

Source strings are **English**; the plugin ships a **Danish** catalog
(`languages/tsb-da_DK.po/.mo`) and follows the active WordPress locale — admin and
front-end switch automatically, defaulting to English. Front-end month and weekday
names come from `wp_date()`, so they match the locale with no extra entries.

Regenerate the template / add a language:

```bash
wp i18n make-pot . languages/tsb.pot --domain=tsb --exclude=vendor,tests,docker
# translate languages/tsb-<locale>.po, then:
msgfmt languages/tsb-<locale>.po -o languages/tsb-<locale>.mo
```

### WPML / Polylang (ICL)

- **The plugin's own strings** use gettext, so WPML/Polylang translate them by
  switching the locale per language — nothing extra needed.
- **Admin-configured strings** (email subjects/bodies, `.ics` title) live in an
  option, so they're registered with **WPML String Translation** under the
  `Timeslot Booking` domain (`TSB_I18N`) and translated on output. Translate them
  under *WPML → String Translation*.
- Each AJAX request carries the visitor's language, so confirmation emails and slot
  labels render in their language.
- The widget's own **Intro text** is Elementor content — translate it with WPML's
  Elementor integration (Translation Editor).
- All of this is no-op-safe: with no multilingual plugin active, the bridge passes
  every string through unchanged.

## Widget styling (Elementor)

`Style` tab sections: **General**, **Calendar**, **Time slots**,
**Form & fields**, **Buttons & messages**. Every text element has a full
Typography group (font family, size, weight, line-height, letter-spacing,
transform…) plus margin/padding dimension inputs; boxed elements (day cells, slot
buttons, inputs, buttons, messages) add border, corner-radius and box-shadow
controls, hover/focus/selected colors, and backgrounds — essentially every visual
property is an input.

Accent color defaults to the theme/Elementor global primary
(`--e-global-color-primary`); cells, inputs and slots inherit the theme text color
via `currentColor`/`color-mix`, so the widget blends into any theme out of the box.

### Front-end flow

1. **Pick a day** on the month grid (unavailable days are shown but not
   clickable).
2. The grid is replaced by that day's **time slots**, with a back arrow in the
   header to return to the days.
3. Picking a slot reveals the **contact form** (a slot must be chosen first); the
   form's *Back* returns to the slot list.

Each step animates in, respecting `prefers-reduced-motion`.

### Email placeholders

`{{name}} {{email}} {{phone}} {{message}} {{date}} {{time}} {{ref}} {{site}}
{{type}} {{meet_url}}` (plus one token per custom form field, by name; `{{old_date}}
{{old_time}}` on the *moved* email) — usable in subjects, bodies, and the `.ics`
title. `{{meet_url}}` is the Google Meet link, populated when the type has video
enabled and Google is connected.

## Session types

Types live in the `tsb_types` option (`TSB_Types`), an ordered list. Each owns its
slot length, weekly availability, holidays, lead time, customer email templates,
reminder window and `.ics` / Meet settings. On upgrade a single **`default`** type
is synthesized from the previous global settings, so existing sites behave exactly
as before until you add more. The widget shows a picker step only when more than
one type is enabled; otherwise it goes straight to the calendar for the single
type.

## Google Calendar & Meet

`TSB_Google` does OAuth2 against one Google account (no SDK — plain `wp_remote_*`).
Setup (one-time):

1. In [Google Cloud Console](https://console.cloud.google.com/apis/credentials),
   create an **OAuth 2.0 Client ID** of type *Web application* and enable the
   **Google Calendar API**.
2. Add the Authorized redirect URI shown on the **Google** settings tab
   (`…/wp-admin/admin-post.php?action=tsb_google_callback`).
3. Paste the client ID + secret into the **Google** tab, save, then **Connect**.

With a type's **Video meeting** toggle on, each booking creates a Calendar event
with `conferenceData.createRequest` → a Meet link (`hangoutLink`), stored on the
booking (`meet_url`, `gcal_event_id`). Moves patch the event time; cancellations
delete it. The link is injected into the confirmation email + `.ics`.

## How it works

- **Slot generation** — `TSB_Availability::build( $type )` walks each day up to the
  type's *days ahead*, skips closed weekdays, holidays, whole-day blocks, then lays
  out the day's open hours: first slot at *open + start offset*, each slot *slot
  length* long, stepped by *slot length + gap*, never running past close —
  dropping blocked, within-lead, and **overlapping** slots. Each returned day
  carries a `count` so the calendar can show availability per date.
- **Holidays** — `TSB_Holidays` fetches public holidays from the free
  [date.nager.at](https://date.nager.at) API for any configured country (ISO
  alpha-2), cached per country+year in a transient (30 days). Denmark also has an
  offline computus fallback (Meeus/Jones/Butcher Easter; Store Bededag only
  pre-2024), so DK keeps working with no network — and the unit tests run with no
  HTTP at all.
- **No overbooking (across types)** — every booking stores its `slot_end`
  (`slot_time` + the type's length at book time). Availability and the admin move
  reject any candidate whose range overlaps an existing active booking of **any**
  type (`existing.slot_time < cand_end AND existing.slot_end > cand_start`), so
  variable-length slots can't collide. `UNIQUE(slot_date, slot_time, active)` is a
  backstop for the exact-start case (`active` is `1` live / `NULL` cancelled, so a
  cancelled slot is rebookable); the public insert runs inside a per-date
  `GET_LOCK` to close the check→insert race.
- **Field storage** — form fields are dynamic, so there are no per-field columns:
  every value is stored in `meta` (JSON, keyed by field name). Phone and the
  human-readable summary are rebuilt on read (`TSB_Availability::phone_from_meta` /
  `summary_from_meta`); search is `meta LIKE`.
- **Spam** — honeypot is always evaluated; reCAPTCHA / hCaptcha tokens are
  verified server-side. v3 enforces the configured minimum score.

## Local dev (Docker)

```bash
docker compose up -d
docker compose logs -f wpcli   # wait for: READY -> http://localhost:8765/wp-admin
```

- Site / admin: http://localhost:8765/wp-admin — `admin` / `admin`
- **MailHog** (catches all outgoing mail incl. `.ics`): http://localhost:8025
- Port override: `WP_PORT=9123 docker compose up -d` (also `MAILHOG_PORT`)
- This plugin is live-mounted; PHP opcache means you must
  `docker compose restart wordpress` to see edits.
- Teardown: `docker compose down` (keep data) or `down -v` (wipe).

`docker/mu-plugins/mailhog.php` is a dev-only must-use plugin that routes
`wp_mail()` to the MailHog container — it is not part of the shipped plugin.

## Tests

Pure-PHP unit tests for the holiday math and slot generation (no WordPress
install needed — `tests/bootstrap.php` shims the few WP functions used).

```bash
composer install
composer test        # or: ./vendor/bin/phpunit
```

## Uninstall

Deleting the plugin (not just deactivating) runs `uninstall.php`, which drops
both tables and removes the `tsb_settings`, `tsb_types` and `tsb_google_token`
options.

## File layout

```
timeslot-booking/
├── timeslot-booking.php          bootstrap: hooks, asset enqueue, AJAX wiring
├── uninstall.php                 drops tables + options on delete
├── includes/
│   ├── class-tsb-db.php          table schema + queries + migration
│   ├── class-tsb-holidays.php    holidays (Nager.Date API + DK computus fallback)
│   ├── class-tsb-availability.php slot generation + interval-overlap guard
│   ├── class-tsb-types.php       session types (per-type config in tsb_types)
│   ├── class-tsb-i18n.php        WPML/Polylang bridge for configured strings
│   ├── class-tsb-ics.php         .ics calendar invite builder
│   ├── class-tsb-emails.php      per-type transactional emails + reminder cron
│   ├── class-tsb-google.php      Google Calendar/Meet OAuth + events
│   ├── class-tsb-ajax.php        get-slots + book endpoints, mail, captcha
│   └── class-tsb-rest.php        REST API (tsb/v1) behind the admin SPA
├── widgets/class-tsb-widget.php  Elementor widget + content/style controls
├── admin/class-tsb-admin.php     menu, SPA mount/enqueue, CSV export
├── assets/booking.js, booking.css      front-end booking widget
├── assets/admin/admin.js, admin.css    admin React SPA
├── languages/                    tsb.pot + tsb-da_DK.po/.mo
└── tests/                        PHPUnit (holiday + availability)
```
