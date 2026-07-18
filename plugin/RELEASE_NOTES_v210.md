# Chamber Appointment System 2.1.0

- Added optional break start/end times for each weekday in Schedule Settings.
- Normal reporting-time calculations pause during the configured break and resume afterward without changing serial order.
- Added chamber-only VIP appointment mode with an exact reporting time.
- VIP appointments do not reserve a normal serial and therefore do not disturb the regular queue.
- Added database migration fields `weekday_breaks` and `is_vip`.
