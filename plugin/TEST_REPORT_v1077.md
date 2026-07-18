# CAS 1.0.77 Build Validation Report

## Automated checks completed in the build environment

| Check | Result |
|---|---|
| PHP lint on all PHP files | Passed |
| JavaScript parser check on `assets/js/cas-public.js` | Passed |
| Plugin version constant | `1.0.77` |
| Manual date fields present in booking view | Passed |
| Live availability polling configuration localized to public JS | Passed |
| Active slot reservation table declared | Passed |
| Legacy unique serial index migration logic present | Passed |
| Data export/import handlers, nonce, privacy confirmation, upload-size guard present | Passed |

## Required staging acceptance tests

1. At 360 px width, open the booking page; select a calendar date and type `05/07/2026` manually. Confirm no horizontal overflow and that serials load after either method.
2. Open the same doctor/date in two separate browsers. Select one serial in both. Confirm booking in browser A, then verify browser B removes that serial on its next refresh and rejects a direct submit.
3. Use the admin Booking Desk/manual booking form at the same time as a patient booking. Confirm exactly one booking succeeds for a serial.
4. Cancel an appointment and verify its serial immediately appears as available. Rebook it and then attempt to restore the original cancelled row; confirm safe rejection.
5. Test each status action: Cancel, No Show, Check In, Complete, Reconfirm, manual edit, and delete.
6. Toggle Bangla/English on login, dashboard, booking, appointments, messages, and diabetes portal pages.
7. Export appointments CSV, patients CSV, and JSON; verify each is restricted to the user’s doctor scope. Import each into a staging copy and verify no SMS is sent.
8. Review WordPress/PHP error logs after all tests.

## Deployment rollback

Keep the original v1.0.76 ZIP and a database backup until all acceptance tests pass. If a critical issue occurs, reinstall v1.0.76 and restore the database backup where needed.
