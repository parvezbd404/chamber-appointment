# Chamber Appointment System 1.0.62

## Daily reconfirmation call tracker

- Added a **Reconfirm Call** column to the admin Appointments list.
- On a date-filtered appointment worklist, a chamber manager can click **Mark Called** after calling a patient for reconfirmation.
- The row then displays a separate **Called** marker and time. This does not change appointment status and does not send SMS.
- The marker is stored against the appointment date. If the appointment is edited to another date, it no longer counts as called for the new date and the new-date list shows **Mark Called** again.
- Existing filters are preserved after marking a patient called.
