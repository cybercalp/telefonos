# Specification: CSRF Protection Reset

## Intent
Prevent permanent login lockouts due to expired or invalid CSRF tokens by ensuring the CSRF validation state is reset upon subsequent GET requests.

## Requirements
1. The system MUST reset the CSRF token validation status when a page is accessed via a non-POST request (e.g., GET).
2. The user login flow SHALL NOT evaluate a prior request's failed CSRF state when a new request is initiated.
3. Subsequent login attempts MUST proceed using a freshly generated CSRF token.

## Scenarios
### Scenario 1: Recovery from CSRF token failure
Given the user has submitted a form with an invalid CSRF token
When the user is redirected back to the login page via a GET request
Then the CSRF validation status MUST be reset to successful/neutral for the new attempt
And the system MUST generate a valid CSRF token.
