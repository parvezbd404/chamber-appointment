# Chamber Appointment System 2.1.2

- Added a Development Mode-only **Add to Waiting List** button in the backend Waiting List screen.
- Administrators can select an active patient and add them to the filtered doctor/date even when regular slots remain available.
- Development test additions suppress outbound waiting-list SMS.
- Added duplicate waiting-entry protection for the same patient, doctor, and date.
- Waiting-list patients can still be promoted to any selected free serial/date in both Live and Development modes.
- Kept nonce, capability, doctor-scope, patient, and date validation on all backend actions.
