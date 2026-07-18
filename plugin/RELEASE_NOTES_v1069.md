# Chamber Appointment System 1.0.69

## Single / Solo Doctor mode

- Added **Plugin Settings → Appointment Setup**.
- Choose between **Multiple Doctors / Chambers** and **Single / Solo Doctor**.
- In solo mode, select one active doctor/chamber. The public booking wizard displays that doctor in a fixed card and hides the doctor dropdown.
- All frontend and MyCareBD mobile appointment requests are server-side pinned to the selected solo doctor.
- Solo mode remains safe if a patient modifies HTML/API data: the configured doctor still applies.
- Added optional specialty display in the fixed doctor card.

Existing multi-doctor behavior remains the default.
