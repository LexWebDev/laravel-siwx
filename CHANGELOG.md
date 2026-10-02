# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `stephenhill/base58` now accepts `^1.1|^2.0`. Version 2 keeps the API and only adds type
  declarations, but the old constraint made the package uninstallable next to anything that
  already required base58 2.

### Added

- A weekly scheduled CI run and a manual trigger, so a framework release or a new advisory that
  breaks installation shows up between pushes rather than in a user's bug report.

## [0.3.0] - 2026-09-03

Fixes One-Click Auth logins against applications that compare the statement with the text they
requested. The verification code is untouched: what changed is which part of the statement line
`SiwxMessage` reports as the statement.

### Changed

- `SiwxMessage::$statement` no longer includes the ERC-5573 recap sentence that WalletConnect
  One-Click Auth appends (`I further authorize the stated URI to perform the following actions on
  my behalf: …`). The sentence is stripped only when a `urn:recap:` entry is present in the
  `Resources` section, which is the sole case in which the wallet generates it. Applications that
  compared `statement` with the text they requested rejected every One-Click Auth login with
  `siwx_invalid_message`; they now match without changes on their side.

### Added

- `SiwxMessage::$signedStatement` — the statement line exactly as signed, recap sentence included.
- `SiwxMessage::$resources` — the entries of the `Resources` section, in order.

## [0.2.0] - 2026-07-31

Adds Laravel 10 and 11 to the supported range. No behaviour changed and no verification code was
touched: the package only ever used framework APIs that are identical across 10 through 13, so
this release is constraints, CI and documentation.

### Added

- Support for Laravel 10 and 11. `illuminate/support` now accepts `^10.0|^11.0|^12.0|^13.0`.
- `guzzlehttp/guzzle` as a suggestion. The Laravel HTTP client needs it, so an application that
  enables EIP-1271 verification needs it too. This was never stated before.
- CI matrix entries for both new branches, bringing it to 12 combinations. Laravel 10 is
  exercised on PHP 8.2 and 8.3, Laravel 11 on 8.2 through 8.4 — the interpreters each branch
  saw during its lifetime.

### Fixed

- `guzzlehttp/guzzle` is now a dev dependency. It used to arrive transitively through
  `orchestra/testbench` 10 and 11, but not through testbench 8, which left the three EIP-1271
  tests failing on Laravel 10 with `Class "GuzzleHttp\Psr7\Response" not found` — `Http::fake()`
  builds a Guzzle response internally.

### Note on Laravel 10 and 11

Both branches are closed and every release in them carries unpatched advisories, `CVE-2026-48019`
among them. That is why 0.1.0 excluded Laravel 11. The reasoning has changed: Composer's advisory
policy blocks building those environments from scratch, which is a CI problem, not a consumer one
— an application already running Laravel 10 keeps the framework it has, and installing this
package does not touch it. CI opts out of the policy through `COMPOSER_NO_BLOCKING=1` on those
jobs alone, and verifies them like any other.

PHP 8.1 is still not supported, even though Laravel 10 permits it. It reached end of life in
December 2025.

## [0.1.0] - 2026-07-30

Initial release. Requires Laravel 12 or 13; Laravel 11 is not supported, because every release
in that branch carries unpatched security advisories that Composer 2.10+ refuses to install.

### Added

- `SiwxParser` — parses EIP-4361 messages for any chain, accepting both a numeric `Chain ID`
  (One-Click Auth via WalletConnect `formatAuthMessage`) and a CAIP-2 one (AppKit), normalising
  to CAIP-2. Handles the `Resources:` section and the single-blank-line form emitted when the
  statement is empty.
- `Verifiers\Eip155Verifier` — secp256k1 recovery over EIP-191 prefixed messages, with legacy
  `v` values of 0/1 and lowercase address normalisation.
- `Verifiers\SolanaVerifier` — ed25519 verification via `ext-sodium`, accepting base58 and
  base64 signatures, preserving base58 address casing.
- `VerifierRegistry` — resolves a verifier by CAIP-2 namespace, honours the enabled namespace
  list, and accepts custom verifiers through `register()`.
- `CacheNonceRepository` — single-use nonces on the Laravel cache; consumption is a single
  atomic read-and-delete.
- `SiwxVerifier` — orchestrates parsing, domain allow list, version, timestamps, signature and
  nonce consumption, and returns a `VerifiedSiwxSession`. The nonce is consumed only after the
  signature check passes.
- `GET /siwx/nonce` behind the `siwx.routes.enabled` flag.
- Optional EIP-1271 support for smart contract wallets through
  `RpcContractSignatureChecker`, disabled by default and only consulted after `ecrecover`
  fails.

### Verified against real wallets

Before release the package was driven end to end by MetaMask and Phantom, in the browser and over
WalletConnect. Those runs confirmed that `Chain ID` arrives in CAIP-2 form, that Phantom returns
base58 signatures, and that base58 must be decoded before base64 — a base58 signature also decodes
as base64, into the wrong number of bytes. A real Phantom capture is kept as a test vector.

One path could not be exercised: no available wallet honoured `wc_sessionAuthenticate`, so CACAO
messages and their `Resources` section are covered by a constructed vector rather than a captured
one. The parser skips that section by construction.

[Unreleased]: https://github.com/LexWebDev/laravel-siwx/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/LexWebDev/laravel-siwx/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/LexWebDev/laravel-siwx/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/LexWebDev/laravel-siwx/releases/tag/v0.1.0
