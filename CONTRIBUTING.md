# Contributing

Bug reports and pull requests are welcome. This is an authentication package, so the bar for
changes to verification logic is high — read the rules below before you write code, they will
save you a rewrite.

## Running the tests

```bash
composer install
vendor/bin/pest
```

A single file, a directory, or one test by name:

```bash
vendor/bin/pest tests/SiwxParserTest.php
vendor/bin/pest tests/Verifiers
vendor/bin/pest --filter="verifies a plain message"
```

You need `ext-sodium` and `ext-gmp`. Without gmp, Composer will not install
`simplito/elliptic-php` at all, so this is not optional.

CI runs the suite against PHP 8.2–8.5 and Laravel 12–13. Passing locally is not the same as
passing in CI — macOS has masked real failures here before. Check the workflow result on your
branch before asking for review.

## Rules that will get a pull request sent back

**Do not edit the test vectors in `tests/Pest.php`.** They are real signatures. Change one
character and they stop verifying, and the suite starts testing nothing. If you need a new
case, generate a new vector with a real key and add it alongside the existing ones.

**Keep the core free of network calls.** `SiwxParser` and the verifiers must be pure. The only
component allowed to make a request is `RpcContractSignatureChecker`, and its tests run under
`Http::fake()`.

**Address normalisation belongs to the namespace verifier.** EVM addresses are lowercased,
Solana base58 addresses are returned untouched, and Bitcoin will have its own rules. Nothing
about address casing goes into shared code — lowercasing a base58 address silently produces a
different account.

**The nonce is consumed after the signature check, never before.** Reversing that order turns
an invalid signature into a way to burn somebody else's nonce. There is a test pinning this;
if you find yourself changing it, stop and open an issue instead.

**Exceptions carry machine codes, not diagnostics.** Throw `SiwxException` with one of the
documented codes. Never put recovered addresses, hashes, or internal state into the message —
it ends up in somebody's API response.

## Adding a chain

The architecture expects this. Implement `Contracts\SignatureVerifier`, register it through
`VerifierRegistry::register()`, and add the CAIP-2 namespace to `config('siwx.namespaces')`.
No changes to the parser or the orchestrator should be necessary; if they are, say so in the
pull request, because that is a design problem worth discussing.

One condition: bring vectors produced by real wallets, not by a script that reimplements what
you think the wallet does. Wallets disagree about what exactly they sign, and that disagreement
is the whole difficulty. `bip122` is out of 0.1.0 for exactly this reason.

## Tests come first

The package was built test-first and stays that way. A pull request that changes behaviour
without a test that fails before the change is not reviewable — there is no way to tell whether
the new code does what you say it does.

## Commits

Short, imperative, lowercase, prefixed by type — `git log` shows the shape:

```
feat: verify Solana ed25519 signatures
fix: drop Laravel 11 support, its releases are blocked by security advisories
```

## Out of scope

These are deliberate omissions, not gaps to fill:

- auth guards and user providers — everybody's user model and token strategy differ
- ready-made `auth` / `bind` / `unbind` routes — same reason
- session storage — the application owns sessions

If you disagree with one of these, open an issue first. A pull request adding them will most
likely be declined, and neither of us wants that after you have written it.

## Security

Do not open a public issue for a vulnerability. [SECURITY.md](SECURITY.md) explains the private
channel.
