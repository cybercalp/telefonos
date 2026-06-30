# Specification: Session Security

## Intent
Enforce secure session practices globally, ensuring session configuration parameters are correctly applied to the session cookie prior to session start, mitigating hijacking and fixation risks.

## Requirements
1. The session identifier MUST be initialized with strict mode enabled.
2. The session cookie configuration MUST be set prior to starting or resuming any PHP session.
3. The session cookie MUST carry the HttpOnly flag.
4. The session cookie MUST carry the SameSite=Lax attribute.
5. The session cookie MUST carry the Secure flag when accessed over an HTTPS connection.

## Scenarios
### Scenario 1: Secure cookie parameters on session initialization
Given the user accesses any authenticated or session-dependent endpoint
When the PHP session is started
Then the session cookie MUST be transmitted with HttpOnly, SameSite=Lax, and Secure flags (if HTTPS)
And the configuration settings MUST be applied prior to the session start.
