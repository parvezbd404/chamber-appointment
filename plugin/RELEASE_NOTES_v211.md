# Chamber Appointment System 2.1.1

## Manual booking validation

- Validates required appointment and patient fields before submission.
- Shows a popup explaining the first missing or invalid value and focuses that field.
- Checks the selected doctor/date against the schedule before submitting, including inactive weekdays and holidays.
- Validates normal serial selection or VIP reporting time according to booking mode.
- Validates existing-patient selection and required new-patient name/mobile details.
- Keeps all existing server-side validation as the final security and data-integrity layer.
