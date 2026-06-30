# Specification: Secure Integrations (TLS/SSL Enforcements)

## Intent
Enforce strict TLS/SSL certificate validation for external integrations (Saviacloud API and Active Directory LDAP) in production environments to protect sensitive data and credentials from Man-in-the-Middle (MitM) attacks.

## Requirements
1. The system MUST validate SSL/TLS certificates for all external API (cURL) requests in the production environment.
2. The system MUST enforce strict certificate validation (demand/verify) for all LDAPS/LDAP STARTTLS connections in the production environment.
3. The system SHOULD support configurable custom CA certificates or CA paths to enable verification against corporate or internal CAs.
4. In non-production environments (e.g., development), the system MAY allow bypassing certificate verification if no trust store is configured.

## Scenarios
### Scenario 1: Production Saviacloud API Request Validation
Given the system is running in the production environment
When a cURL request is sent to the Saviacloud API
Then the system MUST verify the peer certificate and the host name.

### Scenario 2: Production LDAPS Active Directory Verification
Given the system is running in the production environment
When an LDAPS connection is initiated to Active Directory
Then the connection MUST enforce strict TLS certificate validation.
