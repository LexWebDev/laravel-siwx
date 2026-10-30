# Laravel SIWX

[English](README.md) | **Українська**

[![packagist](https://img.shields.io/packagist/v/lexwebdev/laravel-siwx?logo=packagist&logoColor=white&label=packagist)](https://packagist.org/packages/lexwebdev/laravel-siwx)
[![php](https://img.shields.io/packagist/dependency-v/lexwebdev/laravel-siwx/php?logo=php&logoColor=white)](https://packagist.org/packages/lexwebdev/laravel-siwx)
[![laravel](https://img.shields.io/packagist/dependency-v/lexwebdev/laravel-siwx/illuminate%2Fsupport?logo=laravel&logoColor=white&label=laravel)](https://packagist.org/packages/lexwebdev/laravel-siwx)
[![tests](https://img.shields.io/github/actions/workflow/status/LexWebDev/laravel-siwx/tests.yml?branch=main&logo=github&label=tests)](https://github.com/LexWebDev/laravel-siwx/actions/workflows/tests.yml?query=branch%3Amain)
[![license](https://img.shields.io/packagist/l/lexwebdev/laravel-siwx?color=blue)](LICENSE)

Серверна перевірка Sign-In With X для Laravel. Перевіряє підписи гаманців над повідомленнями
[EIP-4361](https://eips.ethereum.org/EIPS/eip-4361) для акаунтів **EVM** та **Solana** і коректно
працює з WalletConnect One-Click Auth.

## Навіщо це потрібно

Наївний верифікатор SIWE працює на десктопі в ідеальному сценарії та ламається, щойно з'являється
справжній гаманець:

- **Заголовок не фіксований.** AppKit будує перший рядок як
  `${domain} wants you to sign in with your ${networkName} account:`, де `networkName`
  береться з конфігурації мережі: `Solana`, `Polygon`, будь-що. Парсер, який шукає слово
  `Ethereum`, відхиляє кожен вхід не з EVM.
- **`Chain ID` — не число.** AppKit передає ідентифікатор CAIP-2 (`eip155:1`,
  `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`), а шлях One-Click Auth будує повідомлення через
  `formatAuthMessage` від WalletConnect і видає звичайне ціле число, як у EIP-4361. Обидва варіанти
  приходять на той самий ендпоінт. Цей пакет приймає обидва й нормалізує їх до CAIP-2.
- **One-Click Auth додає секцію `Resources:`** з можливістю `urn:recap:`. Вона є частиною
  підписаних байтів, тому її треба зберегти для перевірки підпису й ігнорувати під час розбору полів.
- **One-Click Auth переписує statement.** Згідно з [ERC-5573](https://eips.ethereum.org/EIPS/eip-5573)
  гаманець дописує `I further authorize the stated URI to perform the following actions on my
  behalf: (1) 'request': 'personal_sign' …` до statement, який запросив застосунок, а список методів
  у різних гаманців різний. Застосунок, що порівнює statement із запитаним текстом, відхиляє кожен
  мобільний вхід. Цей пакет повертає власний statement застосунку в `statement`, а повний підписаний
  рядок зберігає в `signedStatement`.
- **Регістр адреси залежить від мережі.** Переведення адреси Solana в base58 у нижній регістр дає
  іншу, неіснуючу адресу.

Кожен із цих випадків покрито тестом на реальному векторі підпису.

## Підтримувані мережі

| Простір імен CAIP-2 | Мережі | Схема підпису |
|---|---|---|
| `eip155` | усі мережі EVM | secp256k1 + keccak256, EIP-191, опційно EIP-1271 |
| `solana` | Solana mainnet/devnet | ed25519 через `ext-sodium`, підписи в base58 та base64 |

Bitcoin (`bip122`) навмисно не входить до 0.1.0: BIP-322 вимагає будувати й перевіряти віртуальні
транзакції, а бібліотеки для цього на PHP поки немає. Архітектура до цього готова: можна додати
власний верифікатор, не чіпаючи ядро.

```php
use LexWebDev\Siwx\Contracts\SignatureVerifier;
use LexWebDev\Siwx\VerifierRegistry;

// In a service provider's boot():
app(VerifierRegistry::class)->register(new MyBip122Verifier);
```

Також додайте простір імен до `config('siwx.namespaces')`: реєстр відхиляє простори імен, які не
ввімкнено явно.

## Встановлення

```bash
composer require lexwebdev/laravel-siwx
php artisan vendor:publish --tag=siwx-config
```

## Конфігурація

`SIWX_ALLOWED_DOMAINS` **обов'язкова**. Якщо список дозволених доменів порожній, кожна перевірка
завершується помилкою `siwx_invalid_domain`. Це зроблено навмисно: порожній список безпечніший за
відкритий, а прив'язка до домену якраз і не дає підпису, отриманому на іншому сайті, спрацювати на
вашому.

```dotenv
SIWX_ALLOWED_DOMAINS=app.example,www.app.example
SIWX_NONCE_TTL=600
SIWX_CLOCK_SKEW=300
SIWX_CACHE_STORE=
SIWX_ROUTES_ENABLED=true
SIWX_ROUTES_PREFIX=siwx
SIWX_ROUTES_THROTTLE=60,1
SIWX_EIP1271_ENABLED=false
SIWX_RPC_URL=
```

Вказуйте кожен домен **один раз**, точно так, як гаманець надішле його в рядку `domain`, зокрема з
портом, якщо застосунок працює на порту, наприклад `localhost:3000` під час розробки.

Поле `URI` не звіряється зі списком дозволених окремо. Натомість його authority (хост і порт, якщо
він є) має збігатися з рядком `domain`, як того вимагає EIP-4361 для коректного повідомлення. Це
суворіше, ніж перевіряти обидва значення за списком: за двох дозволених хостів повідомлення могло б
назвати один домен, а в URI вказати на інший.

## Використання

Пакет містить маршрут `GET /siwx/nonce`, який повертає `{"nonce": "..."}`. Nonce одноразові й
спливають через `SIWX_NONCE_TTL` секунд. Вимкніть маршрут через `SIWX_ROUTES_ENABLED=false`, якщо
хочете видавати nonce самостійно.

Маршрут працює під `api` та `throttle:60,1`, бо кожен запит пише в кеш. Налаштуйте ліміт через
`SIWX_ROUTES_THROTTLE` або замініть увесь стек через `siwx.routes.middleware`. За проксі спершу
налаштуйте довірені проксі, інакше всі відвідувачі матимуть одну клієнтську IP-адресу й один ліміт
на всіх.

Маршрутів автентифікації навмисно немає: вони залежать від вашої моделі користувача та стратегії
токенів. Мінімальний контролер:

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

`verify()` кидає `LexWebDev\Siwx\Exceptions\SiwxException` за будь-якої помилки, а в разі успіху
повертає `VerifiedSiwxSession`. Якщо виняток не перехопити, він стає відповіддю `401` з
`{"message": "siwx_invalid_signature"}` і не потрапляє у ваші логи. Колбек `renderable` або власний
`try`/`catch` мають пріоритет.

```php
$session->address;    // normalised per chain
$session->namespace;  // 'eip155' | 'solana'
$session->chainId;    // always CAIP-2, e.g. 'eip155:137'
$session->domain;
$session->issuedAt;   // CarbonImmutable
$session->message;    // the parsed SiwxMessage
```

`SiwxMessage` містить усі розібрані поля. Ось ті, які варто знати, коли ви перевіряєте намір
користувача:

```php
$message->statement;        // what your app asked the wallet to sign, recap sentence removed
$message->signedStatement;  // the statement line exactly as it appears in the signed message
$message->resources;        // entries of the Resources section, e.g. ['urn:recap:…']
$message->scheme;           // optional scheme of the header line, e.g. 'https', or null
$message->requestId;        // the Request ID field, or null
```

Порівнюйте із запитаним текстом `statement`, а не `signedStatement`. На шляху One-Click Auth вони
різняться, і ця різниця залежить від гаманця.

### Зберігання адреси

`$session->address` нормалізується **залежно від мережі**: адреси EVM переводяться в нижній регістр,
а адреси Solana в base58 повертаються байт у байт, бо base58 чутливий до регістру.

Ніколи не зберігайте лише адресу. Той самий рядок може бути дійсним акаунтом у кількох просторах
імен, тому унікальний індекс має охоплювати пару:

```php
$table->string('wallet_namespace');
$table->string('wallet_address');
$table->unique(['wallet_namespace', 'wallet_address']);
```

## Коди помилок

`SiwxException::code()` повертає машинозчитуваний рядок. Жодні внутрішні подробиці в нього не
потрапляють: ви отримуєте код, а не діагностику на кшталт "recovered 0xabc, expected 0xdef".

| Код | Значення |
|---|---|
| `siwx_invalid_message` | повідомлення порушує граматику EIP-4361 (дубльовані чи невідомі поля, багаторядковий statement, час поза форматом RFC 3339, `Chain ID` поза CAIP-2), неправильна версія або не пройдено перевірку часу |
| `siwx_invalid_domain` | `domain` або хост `URI` не входить до списку дозволених, або список порожній |
| `siwx_invalid_nonce` | nonce ніколи не видавався, уже використаний або сплив |
| `siwx_invalid_signature` | підпис не належить заявленій адресі |
| `siwx_unsupported_namespace` | простір імен мережі невідомий або вимкнений у конфігурації |

Nonce гаситься **після** успішної перевірки підпису, тому неправильним підписом не можна спалити
чужий nonce.

## Фронтенд

Використовуйте `SIWXConfig` з `@reown/appkit`: окремий пакет `@reown/appkit-siwx` встановлювати не
потрібно.

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

На шляху One-Click Auth `createMessage` викликається без адреси, тож вивести nonce з гаманця
неможливо: спершу отримайте його з `GET /siwx/nonce`.

### Phantom відмовляється підписувати chain id із genesis-хешем у Solana

AppKit передає в `createMessage` ідентифікатор CAIP-2 `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp`.
Розширення Phantom, за перевіркою від 2026-10-30, не підписує повідомлення з ним: гаманець повертає
помилку "The app's signature request cannot be shown due to invalid formatting", AppKit показує
"Error signing message", і до вашого бекенду нічого не доходить. Натомість пишіть у рядку `Chain ID`
значення `solana:mainnet`:

```js
const signedChainId = chainId === 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp' ? 'solana:mainnet' : chainId
```

Цей пакет приймає обидві форми й повертає значення так, як його підписано, тому в `$session->chainId`
може бути будь-яка з них. Якщо ваша логіка залежить від мережі, порівнюйте з обома.

### One-Click Auth спрацьовує лише в застосунку з одним простором імен

AppKit пробує One-Click Auth (`wc_sessionAuthenticate`, який повертає CACAO і повідомлення, зібране
WalletConnect, а не вашим `createMessage`) лише тоді, коли всі зареєстровані мережі належать
**рівно одному** простору імен, і це `eip155`. Перевірка міститься в
`SIWXUtil.universalProviderAuthenticate`:

```js
const namespaces = new Set(chains.map(chain => chain.split(':')[0]))
if (!siwx || namespaces.size !== 1 || !namespaces.has('eip155')) {
  return false
}
```

Отже, застосунок, який реєструє мережі EVM **і** Solana в одному екземплярі AppKit, ніколи не отримає
CACAO: кожен вхід відкочується до звичайного `personal_sign` повідомлення, зібраного вашим кодом.
Це варто знати, перш ніж шукати помилку в обробці `Resources`: секції `Resources` просто не буде.

Цей пакет перевіряє обидва варіанти, тож налаштовувати нічого не треба. Ця примітка лише про те, що
очікувати побачити в запитах.

Для тестування з цього випливають дві речі. Гаманець без підтримки `wc_sessionAuthenticate` теж мовчки
відкочується, тому відсутність CACAO сама по собі не каже, яка сторона відмовила. А запит надсилається
під час підключення, а не під час підпису: якщо запит на підпис з'являється вже після встановлення
сесії, One-Click Auth не відбувся.

## Гаманці на смартконтрактах (EIP-1271)

Типово вимкнено. Якщо ввімкнути, підпис, який не пройшов `ecrecover`, отримує другий шанс через
`eth_call` до `isValidSignature` на заявленій адресі:

```dotenv
SIWX_EIP1271_ENABLED=true
SIWX_RPC_URL=https://your-rpc-endpoint
```

`SIWX_RPC_URL` обслуговує всі мережі, тому підпис перевіряється в мережі цього ендпоінта, яким би не
був `Chain ID` у повідомленні. Використовуйте його, лише якщо приймаєте одну мережу. В інших випадках
опублікуйте конфігурацію й призначте кожній мережі власний ендпоінт:

```php
'eip1271' => [
    'enabled' => true,
    'rpc_urls' => [
        'eip155:1' => env('SIWX_RPC_URL_MAINNET'),
        'eip155:8453' => env('SIWX_RPC_URL_BASE'),
    ],
],
```

Коли мапу задано, мережа поза нею не проходить перевірку, а `rpc_url` ігнорується.

**Адреса контракту належить одній мережі, а не одному власнику.** Ту саму адресу в двох мережах можуть
контролювати різні сторони. Якщо у мапі більше однієї мережі, ключуйте акаунти контрактних гаманців за
`($session->chainId, $session->address)`, а не лише за адресою, або залиште тільки одну мережу, якій
довіряєте. З `SIWX_RPC_URL` сесія показує мережу, заявлену в повідомленні, а не ту, де відбулася
перевірка, тому в цьому режимі вважайте `$session->chainId` неперевіреним.

Для входів з EOA це нічого не коштує: виклик RPC відбувається лише після невдалого відновлення адреси
і лише для ще чинного nonce, тож запит із вигаданим nonce не витрачає вашу квоту RPC. Помилка RPC
вважається неправильним підписом, а не винятком. Це єдине місце, де пакет звертається до мережі;
замінити реалізацію можна, перевизначивши прив'язку
`LexWebDev\Siwx\Contracts\ContractSignatureChecker`.

## Сумісність

| | |
|---|---|
| PHP | 8.2+, крім Laravel 13, якому потрібен 8.3+ (вимога фреймворку) |
| Laravel | 10, 11, 12, 13 |
| Розширення | `ext-sodium` для ed25519, `ext-gmp` для secp256k1 (вимагає `simplito/elliptic-php`) |

Кожна комбінація з цього діапазону проганяє всі тести в CI.

### Попередження для Laravel 10 та 11

Обидві гілки закриті, і кожен їхній реліз має невиправлені вразливості, серед них `CVE-2026-48019`,
діапазон якої охоплює всі версії 10.x та 11.x. Виправлених релізів у цих гілках немає й не буде. Цей
пакет там працює, і його встановлення ніяк не впливає на цю проблему: фреймворк у вашому застосунку вже
такий, який є. Але оновлення фреймворку варто запланувати.

Є один практичний наслідок: за типовою політикою щодо вразливостей Composer відмовляється встановлювати
ці релізи з нуля. На `composer require lexwebdev/laravel-siwx` в наявному застосунку це не впливає:
фреймворк там уже встановлено, і його не чіпають. Це стосується лише побудови такого середовища з нуля,
тому CI виставляє `COMPOSER_NO_BLOCKING=1` лише для цих завдань.

## Участь у розробці

Звіти про помилки та pull request вітаються. У [CONTRIBUTING.md](CONTRIBUTING.md) описано, як запускати
тести, і правила для коду перевірки. Прочитайте його, перш ніж змінювати верифікатор, бо деякі правила
неочевидні. Участь регулюється [Кодексом поведінки](CODE_OF_CONDUCT.md).

## Безпека

Див. [SECURITY.md](SECURITY.md). Будь ласка, не повідомляйте про вразливості в публічних issue.

## Ліцензія

MIT. Див. [LICENSE](LICENSE).
