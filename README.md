# Timeslot Booking

Custom WordPress + Elementor plugin. Visitors pick a predefined timeslot, fill a
contact form, and book. Availability is generated from per-weekday opening hours,
with Danish public holidays, weekends, and individual slots/days auto-blocked.
Double-booking is prevented at the database level.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Elementor (free) — the booking widget needs it. Without Elementor the plugin
  loads but shows an admin notice and renders no widget.

## Install

1. Copy the `timeslot-booking/` folder into `wp-content/plugins/`.
2. Activate **Timeslot Booking** in *Plugins*. Activation creates two tables
   (`{prefix}_tsb_bookings`, `{prefix}_tsb_blocked`).
3. Configure under **Booking** in wp-admin (see Tabs).
4. Edit a page in Elementor → drag the **Timeslot Booking** widget onto the page.

## Admin (wp-admin → Booking)

| Tab | What it controls |
|-----|------------------|
| **Tilgængelighed** | Per-weekday open/closed + hours, slot length, days ahead, minimum lead time, Danish-holiday blocking |
| **E-mails** | Admin notification + customer confirmation (toggles, templates), custom sender (From), `.ics` calendar invite |
| **Spam-beskyttelse** | none / honeypot / reCAPTCHA v2 / reCAPTCHA v3 (score) / hCaptcha + keys |
| **Blokeringer** | Remove individual times or whole days from availability |
| **Bookinger** | Upcoming + past bookings; cancel / reschedule / delete; CSV export |

### Email placeholders

`{name} {email} {phone} {message} {date} {time}` — usable in subjects, bodies,
and the `.ics` title.

## How it works

- **Slot generation** — `TSB_Availability::build()` walks each day up to
  *days ahead*, skips closed weekdays, holidays, whole-day blocks, then steps the
  day's open hours by *slot length*, dropping blocked, booked, and within-lead
  slots.
- **Danish holidays** — `TSB_Holidays` computes Easter (Meeus/Jones/Butcher) and
  derives all helligdage. Store Bededag is included only for years before 2024
  (abolished as a public holiday from 2024).
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
│   ├── class-tsb-holidays.php    Danish helligdage (computus)
│   ├── class-tsb-availability.php slot generation
│   ├── class-tsb-ics.php         .ics calendar invite builder
│   └── class-tsb-ajax.php        get-slots + book endpoints, mail, captcha
├── widgets/class-tsb-widget.php  Elementor widget + controls
├── admin/class-tsb-admin.php     settings tabs + booking/block management
├── assets/booking.js, booking.css
└── tests/                        PHPUnit (holiday + availability)
```
