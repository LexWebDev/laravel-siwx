## What this changes

<!-- And why. If it fixes an issue, link it. -->

## How it was verified

<!--
Which test fails without this change? Paste the pest output if the change touches verification.
-->

## Checks

- [ ] `vendor/bin/pest` passes locally
- [ ] The test vectors in `tests/Pest.php` are untouched, or a new vector was generated with a real key
- [ ] No new network calls outside `RpcContractSignatureChecker`
- [ ] Address casing rules stayed inside the namespace verifier
- [ ] The nonce is still consumed only after the signature check
- [ ] Exception messages carry a machine code and nothing else

<!--
CI runs PHP 8.2-8.5 against Laravel 12-13. Local green is not CI green — check your branch
before asking for review.
-->
