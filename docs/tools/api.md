# HTTP API

Five endpoints — analyse, batch, audit, scan, countries — for the callers that are not PHP. **Off by
default.**

```php
// config/laranail/phone.php
'api' => [
    'enabled' => true,
    'middleware' => ['api', 'auth:sanctum'],
],
```

In this section: [Turning it on safely](#turning-it-on-safely) · [Endpoints](#endpoints) ·
[Errors](#errors) · [Limits](#limits) · [Route names](#route-names)

## Turning it on safely

The package registers **no routes at all** until config says so. A package that publishes endpoints
by being installed changes an application's attack surface as a side effect of `composer require`,
and the person who notices is rarely the person who ran it.

Three things to decide before enabling it, in order of how much they matter:

> **`middleware` is not authentication.** The default is `['api']` — Laravel's stock group, which is
> throttling and route-model binding. Enabling the API with that alone publishes an endpoint that
> will parse anything anyone sends it. Put `auth:sanctum`, a token middleware or an IP allow-list in
> the list before exposing it.

**The throttle is automatic.** `throttle:{rate}` is appended to whatever middleware is configured
unless the list already contains a throttle, so removing the rate limit also takes an explicit act.
It is **appended**, not prepended, so your authentication runs first — rejecting an unauthenticated
request should not consume its rate-limit budget, or an unauthenticated caller could exhaust the
bucket for everyone sharing the limiter's key.

**A throttle you wrote down is left alone.** Adding a second limiter silently would give the route
two buckets with different keys and an effective rate that is neither of the numbers anyone wrote.

| Key | Default | |
|---|---|---|
| `api.enabled` | `false` | Nothing is registered until this is `true` |
| `api.prefix` | `api/laranail/phone` | URI prefix; route names are fixed |
| `api.middleware` | `['api']` | **Read the warning above** |
| `api.throttle` | `'60,1'` | Appended unless already present; `null` opts out |
| `api.max_batch` | `1000` | Enforced with a 422, never a truncation |
| `api.allow_intel` | `true` | Whether a request may ask for carrier/geocoding/timezone |

## Endpoints

### `POST {prefix}/analyze`

```json
{ "number": "0712 123456", "country": "KE", "intel": false }
```

```json
{
  "data": {
    "input": "0712 123456", "valid": true, "possible": true,
    "reason": "IS_POSSIBLE", "reason_label": "Possible",
    "e164": "+254712123456", "national": "0712 123456",
    "international": "+254 712 123456", "rfc3966": "tel:+254-712-123456",
    "country": "KE", "calling_code": 254, "extension": null,
    "type": "MOBILE", "type_label": "Mobile", "area_code": null,
    "geographic": false, "tel_link": "tel:+254-712-123456"
  }
}
```

An unparseable number is **200 with `valid: false`**, not an error. The request was well formed and
the answer is simply no; `reason_label` is the part you can show to whoever typed it.

`"intel": true` adds `carrier`, `description` and `timezones`. Off by default because each loads its
own prefix-keyed metadata — free for one number, a different cost class for a thousand.

### `POST {prefix}/batch`

```json
{ "numbers": ["+254712123456", "0712 123456", "junk"], "country": "KE" }
```

Returns one object per input under `data` — each the same shape as `analyze`, plus `index` and
`duplicate_of` — and the whole report under `meta`.

### `POST {prefix}/audit`

The same pass with the per-row payload dropped. For "is this list worth importing", which is a
question about the list:

```json
{
  "data": {
    "summary": { "total": 1200, "valid": 1147, "duplicates": 38, "distinct": 1162, "countries": 6 },
    "countries": { "KE": 980, "UG": 141 },
    "types": { "MOBILE": 1102, "UNKNOWN": 53 },
    "reasons": { "TOO_SHORT": 49, "INVALID_COUNTRY_CODE": 4 },
    "duplicates": { "+254712123456": [7, 391] },
    "invalid": [ { "index": 19, "input": "0712", "reason": "TOO_SHORT" } ]
  }
}
```

The invalid rows keep their `index` even though the rest of the payload is gone, because a count
alone is not something anyone can act on.

### `POST {prefix}/scan`

Free text in, the numbers it contains out, with byte offsets so a caller can highlight or redact the
right occurrence rather than the first one.

```json
{ "text": "Call me on 0712 123456", "country": "KE", "leniency": "VALID" }
```

`leniency` is one of `POSSIBLE`, `VALID`, `STRICT_GROUPING`, `EXACT_GROUPING` — see
[Scanner](scanner.md) for which to pick.

### `GET {prefix}/countries`

The numbering plan: every region with its calling code, whether it is NANP, whether numbers are
portable, and its national prefix. `?calling_code=1` narrows to one code — and returns **twenty-odd
regions**, because `+1` is not a country. `?examples=1` adds an example number, mask and
placeholder, and is off by default because that is one metadata load per region across 245 of them.

It exists so every client does not ship its own copy of the world's country list, which is how those
copies go stale.

## Errors

Validation failures are Laravel's standard 422:

```json
{ "message": "The numbers field must not have more than 1000 items.",
  "errors": { "numbers": ["…"] } }
```

There is no `FormRequest` behind them. That class lives in `illuminate/foundation`, which is not
published as a standalone package — depending on it would mean depending on `laravel/framework`
entirely, from a package that otherwise needs a handful of Illuminate components. `Validator::make()`
throws the same exception and Laravel renders the same body.

## Limits

`max_batch` is **enforced, not applied**. A caller that sent 5,000 and got 1,000 back has a bug it
cannot see, so over-sized batches are a 422 naming the field.

Per-value caps: 60 characters for a number, 100,000 for scanned text.

## Route names

Every route is named `laranail.phone.api.{analyze,batch,audit,scan,countries}`, so `route()` resolves
them and the prefix is written down in exactly one place.

```php
route('laranail.phone.api.batch');
```

---

[← Docs index](../../README.md#documentation)
