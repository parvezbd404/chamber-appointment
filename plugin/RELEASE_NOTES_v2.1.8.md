# Chamber Appointment System 2.1.8

- Added WebOTP API support for compatible Android browsers in secure HTTPS contexts.
- Added domain-bound OTP SMS suffix (`@domain #code`) while preserving the configured OTP message template.
- Added browser/OS one-time-code autofill support, numeric normalization, paste support, and automatic verification after the configured OTP digit count is entered.
- Added safe fallback to manual OTP entry on unsupported browsers, denied permission, timeout, or insecure connections.
- Preserved configurable 4–8 digit OTP length, resend countdown, change-mobile workflow, and all existing security checks.
