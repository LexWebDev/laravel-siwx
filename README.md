# Laravel SIWX

[![packagist](https://img.shields.io/packagist/v/lexwebdev/laravel-siwx?logo=packagist&logoColor=white&label=packagist)](https://packagist.org/packages/lexwebdev/laravel-siwx)
[![php](https://img.shields.io/packagist/dependency-v/lexwebdev/laravel-siwx/php?logo=php&logoColor=white)](https://packagist.org/packages/lexwebdev/laravel-siwx)
[![laravel](https://img.shields.io/packagist/dependency-v/lexwebdev/laravel-siwx/illuminate%2Fsupport?logo=laravel&logoColor=white&label=laravel)](https://packagist.org/packages/lexwebdev/laravel-siwx)
[![tests](https://img.shields.io/github/actions/workflow/status/LexWebDev/laravel-siwx/tests.yml?branch=main&logo=github&label=tests)](https://github.com/LexWebDev/laravel-siwx/actions/workflows/tests.yml?query=branch%3Amain)
[![license](https://img.shields.io/packagist/l/lexwebdev/laravel-siwx?color=blue)](LICENSE)

Server-side Sign-In With X verification for Laravel. Verifies wallet signatures over
[EIP-4361](https://eips.ethereum.org/EIPS/eip-4361) messages for **EVM** and **Solana**
accounts, and survives WalletConnect One-Click Auth.

## Why this exists

A naive SIWE verifier works on the desktop happy path and breaks the moment a real wallet
shows up:

- **The header is not fixed.** AppKit builds the first line as
  `${domain} wants you to sign in with your ${networkName} account:`, where `networkName`
  comes from the network config — `Solana`, `Polygon`, anything. A parser that greps for the
  word `Ethereum` rejects every non-EVM login.
- **`Chain ID` is not a number.** AppKit passes a CAIP-2 id (`eip155:1`,
  `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`), while the One-Click Auth path builds the
  message through WalletConnect's `formatAuthMessage` and emits a plain integer per EIP-4361.
  Both arrive at the same endpoint. This package accepts both and normalises to CAIP-2.
- **One-Click Auth appends a `Resources:` section** with a `urn:recap:` capability. It is part
  of the signed bytes, so it must be preserved for verification and ignored for field parsing.
- **Address casing is chain-specific.** Lowercasing a Solana base58 address turns it into a
  different, non-existent account.

Each of those is covered by a test against a real signature vector.

## Supported chains

| CAIP-2 namespace | Chains | Signature scheme |
|---|---|---|
| `eip155` | every EVM chain | secp256k1 + keccak256, EIP-191, optional EIP-1271 |
| `solana` | Solana mainnet/devnet | ed25519 via `ext-sodium`, base58 and base64 signatures |

Bitcoin (`bip122`) is intentionally out of 0.1.0 — BIP-322 requires building and validating
virtual transactions, and there is no PHP library for it yet. The architecture is ready for it:
add your own verifier without touching the core.

```php
use LexWebDev\Siwx\Contracts\SignatureVerifier;
use LexWebDev\Siwx\VerifierRegistry;

// In a service provider's boot():
app(VerifierRegistry::class)->register(new MyBip122Verifier);
```

Also add the namespace to `config('siwx.namespaces')` — the registry refuses namespaces that
are not explicitly enabled.

## Installation

```bash
composer require lexwebdev/laravel-siwx
php artisan vendor:publish --tag=siwx-config
```

## Configuration

`SIWX_ALLOWED_DOMAINS` is **required**. With an empty allow list every verification fails with
`siwx_invalid_domain`. That is deliberate: an empty allow list is safer than an open one, and
the domain binding is what stops a signature obtained on another site from working on yours.

```dotenv
SIWX_ALLOWED_DOMAINS=app.example,www.app.example
SIWX_NONCE_TTL=600
SIWX_CLOCK_SKEW=300
SIWX_CACHE_STORE=
SIWX_ROUTES_ENABLED=true
SIWX_ROUTES_PREFIX=siwx
SIWX_EIP1271_ENABLED=false
SIWX_RPC_URL=
```

List each domain **once**, exactly as the wallet will send it in the `domain` line — including a
port if your app runs on one, e.g. `localhost:3000` during development.

The `URI` field is not matched against the allow list separately. Instead its authority (host, plus
port when present) must equal the `domain` line, which is what EIP-4361 requires of a well-formed
message. That is stricter than checking both against the list: with two allowed hosts, a message
could otherwise name one domain and point its URI at the other.

## Usage

The package ships `GET /siwx/nonce`, which returns `{"nonce": "..."}`. Nonces are single use
and expire after `SIWX_NONCE_TTL` seconds. Disable the route with `SIWX_ROUTES_ENABLED=false`
if you want to issue nonces yourself.

Authentication routes are deliberately not included — they depend on your user model and token
strategy. A minimal controller:

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LexWebDev\Siwx\SiwxVerifier;

public function auth(Request $request, SiwxVerifier $verifier): JsonResponse
{
    $data = $request->validate([
        'message' => ['required', 'string', 'max:4000'],
        'signature' => ['required', 'string', 'max:2000'],
    ]);

    $session = $verifier->verify($data['message'], $data['signature']);

    $user = User::firstOrCreate([
        'wallet_address' => $session->address,
        'wallet_namespace' => $session->namespace,
    ]);

    return response()->json(['access_token' => $user->createToken('siwx')->plainTextToken]);
}
```

`verify()` throws `LexWebDev\Siwx\Exceptions\SiwxException` on any failure and returns a
`VerifiedSiwxSession` on success:

```php
$session->address;    // normalised per chain
$session->namespace;  // 'eip155' | 'solana'
$session->chainId;    // always CAIP-2, e.g. 'eip155:137'
$session->domain;
$session->issuedAt;   // CarbonImmutable
$session->message;    // the parsed SiwxMessage
```

### Storing the address

`$session->address` is normalised **per chain**: EVM addresses are lowercased, Solana base58
addresses are returned byte-for-byte because base58 is case-sensitive.

Never store the address alone. The same string can be a valid account on more than one
namespace, so make the unique index cover the pair:

```php
$table->string('wallet_namespace');
$table->string('wallet_address');
$table->unique(['wallet_namespace', 'wallet_address']);
```

## Error codes

`SiwxException::code()` returns a machine-readable string. Nothing about the internals leaks
into it — you get the code, not a "recovered 0xabc, expected 0xdef" diagnostic.

| Code | Meaning |
|---|---|
| `siwx_invalid_message` | malformed EIP-4361 message, bad version, or a failed timestamp check |
| `siwx_invalid_domain` | `domain` or `URI` host is not in the allow list, or the list is empty |
| `siwx_invalid_nonce` | nonce was never issued, already used, or expired |
| `siwx_invalid_signature` | signature does not belong to the claimed address |
| `siwx_unsupported_namespace` | chain namespace is unknown or disabled in config |

The nonce is consumed **after** the signature check passes, so an invalid signature cannot be
used to burn somebody else's nonce.

## Frontend

Use `SIWXConfig` from `@reown/appkit` — there is no separate `@reown/appkit-siwx` package to
install.

```ts
import { createAppKit } from '@reown/appkit'

createAppKit({
  // ...
  siwx: {
    async getNonce() {
      const { nonce } = await fetch('/siwx/nonce').then((r) => r.json())
      return nonce
    },
    async addSession({ message, signature }) {
      await fetch('/api/auth', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, signature }),
      })
    },
    // getSessions, revokeSession, setSessions per your app
  },
})
```

On the One-Click Auth path `createMessage` is called without an address, so the nonce cannot be
derived from the wallet — fetch it from `GET /siwx/nonce` first.

### One-Click Auth only fires in a single-namespace app

AppKit attempts One-Click Auth — `wc_sessionAuthenticate`, which returns a CACAO and a message
built by WalletConnect rather than by your `createMessage` — only when the networks you register
belong to **exactly one** namespace, and that namespace is `eip155`. The check lives in
`SIWXUtil.universalProviderAuthenticate`:

```js
const namespaces = new Set(chains.map(chain => chain.split(':')[0]))
if (!siwx || namespaces.size !== 1 || !namespaces.has('eip155')) {
  return false
}
```

So an app that registers EVM chains **and** Solana in the same AppKit instance never produces a
CACAO: every sign-in falls back to a plain `personal_sign` of the message your own code composed.
That is worth knowing before you go looking for a bug in your `Resources` handling — there simply
will not be a `Resources` section to handle.

This package verifies both shapes either way, so nothing here needs configuring. The note is about
what you should expect to see on the wire.

Two things follow for testing. A wallet that does not implement `wc_sessionAuthenticate` also falls
back silently, so an absent CACAO does not by itself tell you which side declined. And the request
is sent during connection, not at signing time: if you see a signature prompt arrive after the
session is already established, One-Click Auth did not happen.

## Smart contract wallets (EIP-1271)

Off by default. When enabled, a signature that fails `ecrecover` gets a second chance through
an `eth_call` to `isValidSignature` on the claimed address:

```dotenv
SIWX_EIP1271_ENABLED=true
SIWX_RPC_URL=https://your-rpc-endpoint
```

EOA logins never pay for this — the RPC call only happens after recovery fails. An RPC error
is treated as an invalid signature, not an exception. This is the only place the package
touches the network; swap the implementation by rebinding
`LexWebDev\Siwx\Contracts\ContractSignatureChecker`.

## Compatibility

| | |
|---|---|
| PHP | 8.2+, except Laravel 13, which requires 8.3+ (framework requirement) |
| Laravel | 10, 11, 12, 13 |
| Extensions | `ext-sodium` for ed25519, `ext-gmp` for secp256k1 (pulled in by `simplito/elliptic-php`) |

Every combination in that range runs the full suite on CI.

### A warning if you are on Laravel 10 or 11

Both branches are closed, and every release in them carries unpatched security advisories —
among them `CVE-2026-48019`, whose affected range covers all of 10.x and 11.x. No fixed release
in those branches exists or ever will. This package works there, and installing it changes
nothing about that exposure either way: your application already has the framework it has.
But the framework is what it is, and upgrading it is worth planning for.

One practical consequence: Composer refuses to resolve those releases from scratch under its
default advisory policy. This does not affect `composer require lexwebdev/laravel-siwx` in an
existing application — the framework there is already installed and is not touched. It only
affects building such an environment from nothing, which is why CI sets
`COMPOSER_NO_BLOCKING=1` for those jobs alone.

## Contributing

Bug reports and pull requests are welcome. [CONTRIBUTING.md](CONTRIBUTING.md) covers how to run
the suite and the rules around verification code — worth reading before you touch a verifier,
since a few of them are not obvious. Participation is under the
[Code of Conduct](CODE_OF_CONDUCT.md).

## Security

See [SECURITY.md](SECURITY.md). Please do not report vulnerabilities in public issues.

## License

MIT. See [LICENSE](LICENSE).
