# Chamber Appointment System v1.0.56

## Application mode control

- Added **Live App Mode** and **Development Mode** in **Chamber → OTP Settings**.
- **Live App Mode** uses the configured SMS provider and optional registered-email OTP copy.
- **Development Mode** suppresses all real SMS provider calls and OTP email delivery.
- In Development Mode the generated six-digit OTP is returned only to the current web/app login screen so testing can proceed without spending SMS balance.
- SMS attempts are recorded with `dev_skip` in the SMS log; no provider HTTP request is made.
- SMS balance check is also disabled in Development Mode.

> Turn **Live App Mode** back on before real patients use the site or MyCareBD. Development Mode intentionally displays OTP values.
