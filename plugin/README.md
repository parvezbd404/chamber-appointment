# Chamber Appointment System

A WordPress doctor chamber appointment system with OTP login, SMS notifications, appointment serials, waiting list, reports, printable lists, admin panel, and patient portal.


## Version 1.0.77 operational updates

- The patient booking page uses a single mobile-friendly calendar date selector.
- Availability refreshes automatically while a booking is being completed. Set the refresh interval in **Chamber → Plugin Settings**.
- Active slots are held in a separate reservation table, so cancelling a booking frees its serial immediately without deleting appointment history.
- **Chamber → Booking Desk** links staff to manual booking and daily status actions.
- **Chamber → Secure Data Tools** provides permission-scoped CSV/JSON export and guarded import. Treat all files as protected patient information.

See `RELEASE_NOTES_v1077.md` and `TEST_REPORT_v1077.md` before deploying.

## Installation

1. Run `bash build-plugin.sh`.
2. Upload `chamber-appointment-system.zip` in **Plugins → Add New → Upload Plugin**.
3. Activate the plugin.

On activation the plugin creates 10 custom database tables, adds roles/capabilities, creates one default doctor and schedule, stores default settings, and schedules expired OTP cleanup.

## Initial Setup

Create these pages and add the matching shortcode:

| Page | Shortcode |
|---|---|
| Patient Login | `[cas_patient_login]` |
| Patient Dashboard | `[cas_patient_dashboard]` |
| Book Appointment | `[cas_book_appointment]` |
| My Appointments | `[cas_my_appointments]` |
| Messages | `[cas_messages]` |

Then go to **Chamber → Plugin Settings** and set the page IDs.

## Shortcode Reference

- `[cas_patient_login]` — Mobile OTP login and profile selector.
- `[cas_patient_dashboard]` — Patient dashboard with upcoming appointment.
- `[cas_book_appointment]` — Booking wizard with date, serial, confirmation, and waiting list.
- `[cas_my_appointments]` — Patient appointment history.
- `[cas_messages]` — Patient/chamber message thread.

## Admin Setup Guide

1. Go to **Chamber → Doctors/Chambers** and add doctors.
2. Go to **Chamber → Schedule Settings** and configure limits, time, active days, holidays, batch size, interval, and manual serial picking.
3. Go to **Chamber → SMS Settings** and enter BulkSMSBD credentials.
4. Create the portal pages and set page IDs in Plugin Settings.
5. Test OTP from the patient login page.

## SMS Setup

Use a BulkSMSBD account. Enter API URL, API key, and sender ID in **SMS Settings**. If response code `1032` appears, whitelist your server IP. Use the Test SMS form to confirm delivery.

## Reporting Time Explained

Formula:

```text
reporting_time = start_time + floor((serial_number - 1) / batch_size) * reporting_interval_minutes
```

Example: start 14:00, batch size 10, interval 60 minutes.

| Serials | Reporting Time |
|---|---|
| 1–10 | 14:00 |
| 11–20 | 15:00 |
| 21–30 | 16:00 |
| 31–40 | 17:00 |

## Date-filtered call worklist

When **Chamber → Appointments** is filtered by date, all patient details remain open on small screens. This supports fast reconfirmation calling without requiring the chamber manager to open each individual row.

## Appointment Workflow Diagram

| From | Action | To |
|---|---|---|
| pending | Admin confirms | confirmed |
| confirmed | Admin reconfirms | reconfirmed |
| confirmed/reconfirmed | Patient arrives | checked_in |
| checked_in | Consultation done | completed |
| any active | Cancel | cancelled |
| any active | No show | no_show |
| waiting list | Promote | moved_from_waiting |

## Roles & Access Table

| Role | Settings | Appointments | Patients | Reports | SMS |
|---|---:|---:|---:|---:|---:|
| Administrator | Yes | Yes | Yes | Yes | Yes |
| Chamber Manager | No | Yes | Yes | Yes | Yes |
| Chamber Attendant | No | Yes | Yes | No | No |

## Database Schema Summary

- `wp_cas_patients`
- `wp_cas_patient_family_members`
- `wp_cas_doctors`
- `wp_cas_schedules`
- `wp_cas_appointments`
- `wp_cas_waiting_list`
- `wp_cas_sms_logs`
- `wp_cas_otp_logs`
- `wp_cas_messages`

## Security Notes

The plugin uses nonces, capability checks, sanitized input, escaped output, `$wpdb->prepare()`, OTP hashing with `wp_hash_password()`, Bangladeshi mobile normalization, SMS API key hiding, and appointment duplicate protection with transactions plus a unique key.

## Troubleshooting FAQ

1. OTP not arriving: check SMS credentials, balance, and IP whitelist.
2. Date cannot be booked: check doctor schedule, active days, holidays, and daily limit.
3. Duplicate serial error: confirm the unique key exists and avoid manual DB edits.
4. Invalid number: use `01XXXXXXXXX` or `8801XXXXXXXXX`.
5. Patient portal logs out: disable caching on portal pages and confirm PHP sessions work.

## Extending the Plugin

Recommended hooks:

- `cas_before_booking`
- `cas_after_booking`
- `cas_sms_sent`
- `cas_otp_verified`
- `cas_status_changed`

## 1.0.3 Patch Notes

- Added visible field labels in backend forms.
- Added edit/deactivate/delete actions for doctors and patients.
- Added edit/delete support for appointments.
- Fixed frontend serial tile visibility and separated serial number from reporting time.
- Added back buttons in the appointment booking wizard.


## Version 1.0.9 Role Scoping Update

This version adds standard operational roles and assigned-doctor access control. Version 1.0.10 also force-registers Doctor and Receptionist roles on admin load so they appear even when the plugin is updated over an existing active install.

### Roles

- `administrator`: full access.
- `chamber_manager`: daily operations across all doctors; no core plugin settings.
- `cas_doctor`: own/assigned doctor appointment lists, reports, patient list, and printable lists.
- `chamber_attendant`: assigned-doctor appointment and patient operations.
- `receptionist`: assigned-doctor appointment booking, waiting list, and patient operations.

### Assigning Doctors to Users

Go to **Users → Edit User → Chamber Appointment Access** and tick one or more doctors under **Assigned Doctor(s)**.

Doctor, attendant, and receptionist users only see appointments, waiting lists, reports, booking options, and print lists for assigned doctors. Administrators and chamber managers see all doctors.

## Version 1.0.11 Update Notes

This update preserves existing implemented features and adds the missing requirements from the latest specification:

- Frontend patient profile management from the Patient Dashboard.
- Frontend family-member creation under the same verified mobile number.
- Booking can now be made for the logged-in patient or any same-mobile family/patient profile.
- My Appointments shows appointments for all same-mobile patient profiles and includes a Waiting List Status section.
- Date availability AJAX now returns active-day, holiday, and available serial information for the selected month.
- Backend printable list now supports Confirmed or Reconfirmed status selection by date and doctor.
- SMS Settings now includes an optional Balance Check API URL and AJAX balance-check button.

The plugin still keeps the already implemented role scoping, Doctor/Receptionist roles, check-in time storage, reconfirmed list printing, SMS fixes, OTP resend countdown, and all previous admin/frontend workflows.

## Diabetes Care Treatment Plan Workflow

Version 1.0.32 adds **Diabetes Review & Treatment Adjustment** under the Diabetes Care admin menu.

- Doctors can review diagnosis, current treatment plan, current insulin doses, recent blood sugar records, and last advice.
- Doctors can save a doctor-only draft or publish updated advice to the patient.
- When published, the new treatment plan becomes the active/current treatment plan and the previous active plan moves to treatment history.
- The patient-facing Diabetes Care Portal uses `[cas_diabetes_portal]` and shows current diagnosis, treatment plan, insulin dose, latest advice, next blood sugar check plan, recent blood sugar records, and treatment history.


## Version 1.0.39 Update Notes

This version updates the Diabetes Review & Treatment Adjustment treatment plan display to show insulin doses in compact clinical format, for example `20+0+10`, while keeping detailed dose fields editable in the treatment adjustment form.


## Version 1.0.40 Update Notes
- Added Bangla number keypad popup beside insulin dose fields in Diabetes Review & Treatment Adjustment.
- Keypad supports Morning, Pre-lunch, Pre-dinner, and Bedtime fields for both short/ultra-short and basal insulin blocks.
- Server-side insulin dose parsing now accepts Bangla digits and saves them as numeric values.

## Version 1.0.42 Update Notes

- GLP-1/DPP4 frequency now switches to Morning/Noon/Night dose boxes only when Form contains Tablet or Capsule.
- Non-tablet/capsule GLP-1/DPP4 frequency uses the uploaded Frequency.xlsx value list with datalist autocomplete.
- GLP-1/DPP4 Duration uses the uploaded duration2.xlsx value list plus keypad quick tokens.


## Version 1.0.43 Update Notes
- Added an Edit Value List button beside the GLP-1/DPP4 Frequency field when Form is not Tablet/Capsule.
- Frequency options can now be edited directly from the Diabetes Review & Treatment Adjustment page and are saved as a WordPress option.


## Version 1.0.44 Update Notes
- GLP-1/DPP4 Duration keypad now includes a Continue button that enters চলবে.



## Version 1.0.46 Update Notes

- Reduced visible width of Brand/Drug autocomplete fields in Diabetes Review & Treatment Adjustment for:
  - Short / Ultra-short insulin
  - Intermediate / Basal insulin
  - GLP-1/DPP4
  - Oral Diabetes Medicines
  - Other Medicines
- Kept autocomplete lookup, strength/form auto-fill, and keypad behavior unchanged.

## Version 1.0.45 Update Notes

- Fixed GLP-1/DPP4 Duration keypad: added a clearly visible full-width `Continue / চলবে` button.
- Clicking the button inserts `চলবে` into the Duration field.


## Version 1.0.48 Update Notes

- Increased GLP-1/DPP4, Oral Diabetes Medicines, and Other Medicines Duration keypad popup width.
- Duration keypad now uses a wider 4-column layout so Bangla duration tokens display properly.
- Added safer screen-edge positioning for the Duration keypad popup.

## Version 1.0.47 Update Notes

This version updates the Diabetes Review & Treatment Adjustment page so Oral Diabetes Medicines and Other Medicines use the same field style as GLP-1/DPP4: Medicine name, Strength, Form, conditional Frequency fields for Tablet/Capsule forms, non-oral frequency value list with Edit Value List, and Duration with keypad/value list. The separate Unit dropdown is removed from the visible OAD/Other medicine rows.


## Version 1.0.50 Update Notes

- Adds SMS/email notification status below the **Publish Advice to Patient** button.
- Adds Diabetes Care settings to enable/disable SMS and email sending for published advice notifications.

## Version 1.0.52 Mobile Patient Care + Batch Submission Update

- Added secure mobile REST API endpoints for appointment listing, doctor listing, available serials, booking, and patient cancellation.
- Added mobile REST API batch submission endpoint for Diabetes Care. It validates every required blood sugar, BP, weight, and exercise field before any records are saved.
- The web Diabetes Care Portal now has one **Submit All Required Data** button for every due monitoring section. Missing fields are highlighted with a standard warning before submission.

## Mobile app notifications (v1.0.54)

The Patient Care app can register an Expo Push Token after OTP login. The plugin stores an in-app alert history and sends remote notifications for appointment booking/status changes, Diabetes Care enrollment, daily data reminders, and doctor-published treatment updates.

## MyCareBD direct FCM push (v1.0.55)

This release supports MyCareBD Android Studio APKs that register native Firebase Cloud Messaging device tokens. Configure **Chamber → MyCareBD Push Notifications** with your Firebase project ID and Firebase service-account JSON to enable direct FCM V1 delivery. The plugin still keeps a permanent in-app Alerts history and supports existing Expo-token registrations during migration.


## v1.0.56 application mode

Use **Chamber → OTP Settings → Application Mode**. Development Mode suppresses external SMS API/email OTP delivery and exposes the generated OTP only on the active web/app login screen for testing. Return to Live App Mode before production use.


### v1.0.65
- Patient booking now automatically loads available serials after selecting doctor and appointment date.


### v1.0.67
- Rebuilt the patient appointment page into a clear four-step journey: Date → Serial → Patient → Confirm.
- Preserved automatic serial loading, waiting list, family-member booking, one-active-appointment rule, reconfirmation lock, and existing backend/mobile workflows.
- Added a responsive, reference-inspired mobile booking layout with large serial cards and direct change controls on the final review screen.

### v1.0.68
- Fixed the mobile confirmation step so Date, Serial/Reporting Time, and Patient Information stay readable in normal horizontal text.
- Kept the Change buttons compact so they no longer force booking details into narrow one-character columns.


## v1.0.69
Plugin Settings now supports Single / Solo Doctor and Multiple Doctors / Chambers booking modes.


## v1.0.70

- Repaired the appointment review/confirmation layout on mobile browsers.
- Date, serial/reporting time and patient information now remain readable in a full-width detail area.
- Each Change button stays compact and no longer compresses text into vertical characters.


## v1.0.71

- Repaired the automatic available-serial loading trigger after selecting a date.
- Added date input, change and blur fallback listeners for mobile date pickers.
- Added a safe retry button only if an automatic serial request fails, with clearer error feedback.
