# Chamber Appointment System v1.0.74

## Quick relative profile during appointment booking

- Added an **Add a relative** button beside **Booking for** in Step 3 of the frontend appointment flow.
- Opens a compact inline form for relative name, relation, age, gender, and blood group.
- The new relative is saved under the same mobile-number account and automatically selected for the current appointment.
- Keeps the selected doctor, date, and serial intact.
- Uses the existing nonce-protected family-member AJAX action and patient profile access rules.
