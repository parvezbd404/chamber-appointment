# Version 1.0.58 — One Active Appointment Per Patient

- A mobile account may manage multiple patient profiles.
- Each individual patient profile may have only one active appointment at a time.
- A patient can book another appointment after the current appointment date has passed, or after it has been cancelled, completed, or marked no-show.
- Eligible appointments can be modified or cancelled from **My Appointments**.
- Appointment modification uses the same doctor → date → serial workflow and safely releases the existing serial during selection.
- Mobile REST API adds `POST /wp-json/cas-dc/v1/appointments/update`.
