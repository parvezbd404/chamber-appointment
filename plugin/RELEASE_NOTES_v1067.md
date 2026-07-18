# Chamber Appointment System v1.0.67

## Reference-style appointment journey

The patient booking page has been rebuilt as a clear four-step journey while preserving existing CAS appointment, waiting-list, OTP, SMS, Diabetes Care, mobile API, and chamber-manager workflows.

1. Choose doctor and date
2. Choose available serial
3. Choose self/family patient profile
4. Review and confirm

### Included behavior

- Available serials open automatically after a valid doctor/date selection.
- Mobile account holders can select the correct self/family patient profile after selecting a serial.
- The one-active-appointment-per-patient rule is checked at patient selection and confirmation.
- Reconfirmed appointments remain locked from patient-side modification/cancellation.
- Fully booked dates can still move through the same patient/confirmation flow to join the waiting list.
- Confirmation shows clear Change controls for date, serial, and patient.
- Booking forms use a safe responsive layout without negative viewport margins or content clipping.
