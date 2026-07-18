# Chamber Appointment System 2.1.3

- Repairs legacy appointment status/source ENUM definitions during upgrade.
- Ensures the VIP and patient modification columns exist on older installations.
- Retries a failed appointment insert once after schema repair.
- Fixes waiting-list promotion failures showing “Could not create appointment.”
