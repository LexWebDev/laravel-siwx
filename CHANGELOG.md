# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/LexWebDev/laravel-siwx/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/LexWebDev/laravel-siwx/releases/tag/v0.1.0
