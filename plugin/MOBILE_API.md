# Patient Care Mobile REST API (v1.0.53)

Base path: `/wp-json/cas-dc/v1`

All endpoints below require `Authorization: Bearer <mobile-session-token>` except the OTP endpoints and status endpoint.

## OTP

- `POST /login/send-otp` — `{ "mobile": "8801..." }`
- `POST /login/verify-otp` — `{ "mobile": "8801...", "otp": "123456", "device_name": "Expo android" }`
- `POST /logout`
- `GET /patients`

## Appointments

- `GET /appointments/doctors`
- `GET /appointments/available-slots?doctor_id=1&date=YYYY-MM-DD`
- `GET /appointments?patient_id=123`
- `POST /appointments/book` — `{ "patient_id":123, "doctor_id":1, "appointment_date":"YYYY-MM-DD", "serial_number":4 }`
- `POST /appointments/update` — `{ "appointment_id":123, "doctor_id":1, "appointment_date":"YYYY-MM-DD", "serial_number":5, "notes":"" }`
- `POST /appointments/cancel` — `{ "appointment_id":123 }`

## Diabetes Care

- `GET /diabetes/profile?patient_id=123`
- `GET /diabetes/current-treatment?patient_id=123`
- `GET /diabetes/submissions?patient_id=123`
- `GET /diabetes/history?patient_id=123`
- `POST /diabetes/submit-batch` — `{ "patient_id":123, "submissions":[...] }`

`submit-batch` validates every required field in the batch and saves all entries in a transaction, so a missing value does not result in partial submission.

## Mobile notifications

- `POST /notifications/register-device` — `{ "expo_push_token":"ExpoPushToken[...]", "platform":"android", "device_name":"Patient Care App" }`
- `GET /notifications?limit=100`
- `POST /notifications/mark-read` — `{ "ids":[1,2,3] }`; omit `ids` to mark all notifications read.

The plugin creates an in-app notification for these events and sends an Expo push notification when the app has registered an active device:

- appointment booking and appointment status changes
- Diabetes Care enrollment
- Diabetes Care data-due/overdue reminders from daily cron
- doctor-published Diabetes Care treatment plans
