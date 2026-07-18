# Chamber Appointment System v1.0.54 — MyCareBD mobile update

## Added

- MyCareBD-compatible new patient registration REST endpoint (`/registration/complete`) after verified mobile OTP.
- Per-device notification preferences for appointment alerts, Diabetes Care alerts, blood sugar/BP/weight/exercise reminders, and treatment-plan updates.
- In-app notification records now record notification type and the result of the Expo push attempt.
- Appointment booking/status events and Diabetes Care enrollment, reminders, and published treatment plans create both an in-app alert and a background Android push attempt.

## Android push prerequisites

Use app package `bd.com.drparvez.mycarebd`; configure Firebase Cloud Messaging V1 credentials in Expo EAS. The WordPress host must permit outbound HTTPS connections to Expo Push Service.
