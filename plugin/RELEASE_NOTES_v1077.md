# Version 1.0.77 — Live Serial Safety, Mobile Booking, Bangla & Data Tools

## What changed

### Booking date entry and mobile usability
- The booking form now provides both the native calendar picker and a manual date field.
- Manual dates accept `DD/MM/YYYY` and `YYYY-MM-DD`, validate real calendar dates, and synchronize with the calendar field.
- The booking layout is adjusted for narrow screens, including 360 px widths: date inputs stack vertically, serial tiles remain tap-friendly, and very narrow screens use a single serial column.

### Real-time availability
- While a patient is on the **Serial** or **Confirm** step, the page polls the server for slot changes. The default interval is 15 seconds and can be changed in **Chamber → Plugin Settings** (5–60 seconds).
- When a selected serial remains free, it stays selected after refresh. If another user books it first, the selection is cleared and the patient is asked to select another serial.
- Returning to a browser tab triggers an immediate availability refresh.

### Double-booking protection and cancellations
- Added the `wp_cas_appointment_slots` table, with a database primary key on doctor, date, and serial. It is the authoritative reservation for active slots.
- Booking, editing, staff booking, status restore, cancellation, and deletion now use transaction-based reservation handling.
- The previous legacy unique appointment index is upgraded to a normal lookup index. This is necessary because a permanent unique index prevented a cancelled serial from being rebooked.
- Cancellation releases the active-slot reservation immediately but keeps the cancelled appointment audit record. Restoring a cancelled appointment fails safely when its serial has already been rebooked.
- A request whose serial is no longer available is rejected; it is never silently reassigned to a different serial.

### Admin workflow
- Added **Chamber → Booking Desk**, a quick route to manual booking and the daily status list.
- Existing appointment actions continue to provide Cancel, No Show, Check In, Complete, Reconfirm, and manual booking. Cancellation now frees the serial through the active-slot reservation layer.

### Bangla / English frontend
- Added a patient-facing Bangla/English switcher on portal pages.
- Bangla is the default frontend language setting and can be changed from **Chamber → Plugin Settings**.
- The core booking, portal navigation, common status, date, confirmation, and conflict messages are translated. Legacy/admin-only text is intentionally unchanged where an English administrative workflow is already in use.

### Privacy-aware export and import
- Added **Chamber → Secure Data Tools**.
- Exports support appointment CSV, patient CSV, and a JSON backup containing patients plus appointments.
- Exports are scoped to the user’s allowed doctors, marked no-index/no-follow, and are generated directly as a download.
- Imports require `manage_cas_settings`, a nonce, an explicit privacy confirmation, a genuine uploaded CSV/JSON file, and a 5 MB maximum size. Uploaded files are read from PHP’s temporary upload location only and are not retained by the plugin.
- Patient records are matched by mobile number. Import does not send SMS. Invalid records, occupied serials, unavailable doctors, and malformed rows are skipped rather than overwritten.

## Automatic upgrade behavior

On update to 1.0.77 the plugin:
1. Creates the active-slot table.
2. Changes the old permanent serial uniqueness index to a lookup index.
3. Rebuilds active reservations from non-cancelled existing appointments.

Take a database backup before installing, as with every production plugin update. The accompanying v1.0.76 ZIP is retained as rollback media. If an upgrade fails, deactivate/delete only the new plugin files, reinstall the v1.0.76 ZIP, and restore the database backup if database migration was interrupted.

## Validation performed before packaging

- PHP syntax lint passed for every plugin PHP file.
- JavaScript syntax check passed for `assets/js/cas-public.js`.
- Source review confirmed that frontend booking no longer falls back to an arbitrary open serial when the requested serial is unavailable.
- Full browser, WordPress, and database-concurrency testing still must be run on staging before production deployment.
