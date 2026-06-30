# Specification: Test Integrity

## Intent
Ensure the PHPUnit test suite executes genuine correctness assertions instead of tautological checks, and verify configuration and autoloader integrity accurately.

## Requirements
1. Autoloader verification MUST assert that the target classes (specifically BaconQrCode\Encoder\Encoder) are successfully loaded into the PHP runtime.
2. The test suite SHALL NOT use tautological assertions like `assertTrue(true)` to denote autoloader success.
3. Global configuration assertions MUST check for the precise existence of keys in the `$GLOBALS` array using standard array key lookup functions instead of nullability checks.
4. The configuration system MUST respect dynamic overrides defined in configuration files (such as `private/config.ini`) and update corresponding global values.

## Scenarios
### Scenario 1: Verify actual class autoloader verification
Given the autoloader testing script is executed
When checking if the BaconQrCode Encoder class is loaded
Then the test MUST assert that the class exists in the runtime environment.

### Scenario 2: Verify dynamic configuration override
Given the configuration file private/config.ini defines curl_ca_bundle
When the system initializes configurations
Then the global configuration MUST reflect the value from private/config.ini.

### Scenario 3: Verify strict configuration existence checking
Given the global configuration is loaded
When checking for curl_ca_bundle definition
Then the test MUST assert the exact key exists in the globals array.
