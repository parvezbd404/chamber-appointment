# MyCareBD Mobile App Support — Plugin v1.0.54

The MyCareBD Android app uses the secure REST namespace:

`/wp-json/cas-dc/v1`

## Authentication and registration

- `POST /login/send-otp`
- `POST /login/verify-otp`
- `POST /registration/complete` — requires the mobile Bearer token returned after OTP verification; creates the first standard patient profile under that verified mobile number
- `POST /logout`
- `GET /patients`

`/registration/complete` accepts `full_name`, `age`, `gender`, `blood_group`, `city`, `email`, and `address`. Full name, age, and gender are required. Address is optional and can be completed later.

## App notification endpoints

- `POST /notifications/register-device`
- `GET /notifications`
- `POST /notifications/mark-read`
- `GET /notifications/preferences`
- `POST /notifications/preferences`

The plugin saves every supported appointment/Diabetes Care event in `wp_cas_mobile_notifications`. It independently attempts Android push delivery through Expo Push Service for each active registered device. Notification-category preferences are stored per device in `wp_cas_mobile_push_devices`.

Push types:

- `appointment`
- `diabetes`
- `diabetes_reminder`
- `treatment_update`

## Android background push requirements

1. The user must install a real MyCareBD APK, not rely on a simulator.
2. Android notification permission must be granted.
3. The app must register an Expo Push Token after OTP login.
4. The Expo/EAS project must have Firebase Cloud Messaging V1 credentials for `bd.com.drparvez.mycarebd`.
5. WordPress hosting must allow outbound HTTPS requests to `https://exp.host/`.

In-app Alerts remain available in the app even when a device cannot receive a remote push notification.


## Direct Firebase Cloud Messaging (v1.0.55)

MyCareBD Android Studio builds now send the native FCM device token in `push_token` with `push_provider: "fcm"`. The same registration endpoint remains: `POST /notifications/register-device`.

Configure Firebase in **Chamber → MyCareBD Push Notifications** with the project ID and Firebase service-account JSON. The server sends FCM V1 messages directly. Existing Expo tokens remain supported during migration.


### Appointment mode
`GET /appointments/doctors` now returns `appointment_mode` (`single` or `multiple`) and `single_doctor_id`. In single mode all appointment booking, update, and availability requests use the configured solo doctor server-side.
