# Timeslot Booking

Custom WordPress + Elementor plugin. Visitors **pick a day** on a month calendar
(days with free times are highlighted; days without are shown but not clickable),
**then pick a timeslot** (animated reveal), **then** fill the contact form on a
separate step with a back button. Availability is generated from per-weekday
opening hours (slot length, start offset, and gap between slots all configurable),
with public holidays (any country, via date.nager.at), weekends, and individual
slots/days auto-blocked. Double-booking is prevented at the database level.
Bookings appear in their own top-level **Bookings** menu as a native, sortable,
searchable list.

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

**Bookinger** is a top-level menu (like Posts/Pages):

- **Alle bookinger** — native `WP_List_Table`: sortable columns, search, bulk
  cancel/delete, per-row Flyt (reschedule) / Aflys / Slet, CSV export.
- **Indstillinger** — the settings tabs below.

| Tab | What it controls |
|-----|------------------|
| **Availability** | Base business hours; per-weekday open/closed (follow base or own hours); slot length, **start offset**, **gap between slots**; days ahead; minimum lead time; holiday blocking + **country list** |
| **Form** | Toggle/require phone, message and one custom field; **GDPR consent** checkbox (text + optional link, server-enforced) |
| **Emails** | Admin notification + customer confirmation (toggles, templates), custom sender (From), `.ics` calendar invite |
| **Spam protection** | none / honeypot / reCAPTCHA v2 / reCAPTCHA v3 (score) / hCaptcha + keys |
| **Blocks** | Remove individual times or whole days from availability |

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

`{name} {email} {phone} {message} {date} {time}` — usable in subjects, bodies,
and the `.ics` title.

## How it works

- **Slot generation** — `TSB_Availability::build()` walks each day up to
  *days ahead*, skips closed weekdays, holidays, whole-day blocks, then lays out
  the day's open hours: first slot at *open + start offset*, each slot *slot
  length* long, stepped by *slot length + gap*, never running past close —
  dropping blocked, booked, and within-lead slots. Each returned day carries a
  `count` so the calendar can show availability per date.
- **Holidays** — `TSB_Holidays` fetches public holidays from the free
  [date.nager.at](https://date.nager.at) API for any configured country (ISO
  alpha-2), cached per country+year in a transient (30 days). Denmark also has an
  offline computus fallback (Meeus/Jones/Butcher Easter; Store Bededag only
  pre-2024), so DK keeps working with no network — and the unit tests run with no
  HTTP at all.
- **No double-booking** — `UNIQUE(slot_date, slot_time, active)` on the bookings
  table. `active` is `1` for live bookings and `NULL` when cancelled, so a
  cancelled slot frees up and is rebookable while live double-booking is blocked
  at the DB layer (race-safe).
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
both tables and removes the `tsb_settings` option.

## File layout

```
timeslot-booking/
├── timeslot-booking.php          bootstrap: hooks, asset enqueue, AJAX wiring
├── uninstall.php                 drops tables + options on delete
├── includes/
│   ├── class-tsb-db.php          table schema + queries
│   ├── class-tsb-holidays.php    holidays (Nager.Date API + DK computus fallback)
│   ├── class-tsb-availability.php slot generation
│   ├── class-tsb-i18n.php        WPML/Polylang bridge for configured strings
│   ├── class-tsb-ics.php         .ics calendar invite builder
│   └── class-tsb-ajax.php        get-slots + book endpoints, mail, captcha
├── widgets/class-tsb-widget.php  Elementor widget + content/style controls
├── admin/
│   ├── class-tsb-admin.php       menu, settings tabs, booking/block management
│   └── class-tsb-bookings-table.php  native WP_List_Table for bookings
├── assets/booking.js, booking.css
├── languages/                    tsb.pot + tsb-da_DK.po/.mo
└── tests/                        PHPUnit (holiday + availability)
```
