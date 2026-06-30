# Specification: Robust Static Code Scanning

## Intent
Provide robust static scan validation of PHP code sequences that is fully immune to code formatting, whitespace variations, and comment additions.

## Requirements
1. The static scanner helper MUST parse target PHP source files using native tokenization (`token_get_all`).
2. The scanner MUST ignore whitespace tokens (`T_WHITESPACE`), comment tokens (`T_COMMENT`), and docblock comment tokens (`T_DOC_COMMENT`).
3. The static validation suite MUST search for specific token or string sequences in the source file.
4. Static scans for cURL and stream SSL configurations MUST verify sequence match accurately regardless of code formatting.

## Scenarios
### Scenario 1: Formatting-immune sequence detection
Given a PHP source file with non-standard formatting and comments
When the static scanner checks for a specific sequence of tokens
Then the scanner MUST return success if the sequence matches.

### Scenario 2: Static scan for SSL enforcement
Given a PHP script defining secure stream context options
When the SSL enforcement static scan runs
Then it MUST successfully verify that the options are configured using the token sequence match.
