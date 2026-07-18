Chamber Appointment System 2.0.2 — Appointment-only build

This package removes the Diabetes Care module from the original combined plugin.
Included: OTP patient portal, patient/family profiles, appointment booking, schedules, waiting list, messages, SMS, reports, mobile appointment API, and appointment notifications.
Excluded: diabetes enrollment, glucose/BP/weight monitoring, prescriptions, treatment plans, diabetes review, diabetes cron reminders, and diabetes portal routes/views.

The plugin retains the same folder/slug so it can replace the earlier plugin. Back up the site and database first, test on staging, deactivate the old plugin, then upload this package. Existing diabetes database tables are not deleted automatically to avoid accidental clinical-data loss.
