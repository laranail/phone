# Expose the HTTP API

Turn on the endpoints, and put something in front of them first.

## Enable it

```bash
php artisan vendor:publish --tag=laranail::phone-config
```

```php
// config/laranail/phone.php
'api' => [
    'enabled' => true,
    'prefix' => 'api/laranail/phone',
    'middleware' => ['api', 'auth:sanctum'],
    'throttle' => '60,1',
    'max_batch' => 1000,
    'allow_intel' => true,
],
```

```bash
php artisan route:list --name=laranail.phone.api
```

Nothing is registered until `enabled` is `true`, so an install that never sets it adds no routes at
all.

## Authenticate it

> The default `['api']` is **not** authentication. It is Laravel's stock group — throttling and
> route-model binding — and enabling the API with it alone publishes an endpoint that will parse
> anything anyone sends it.

Pick whichever the application already uses:

```php
'middleware' => ['api', 'auth:sanctum'],           // token or SPA session
'middleware' => ['api', 'auth:api'],               // Passport
'middleware' => ['api', 'internal-network-only'],  // your own IP allow-list
```

The throttle is appended after these, so an unauthenticated request is rejected before it consumes
rate-limit budget. Getting that order wrong lets an anonymous caller exhaust the bucket for everyone
sharing the limiter's key.

## Call it

```bash
curl -X POST https://app.test/api/laranail/phone/analyze \
  -H 'Authorization: Bearer …' -H 'Accept: application/json' \
  -d '{"number":"0712 123456","country":"KE"}'
```

```js
const res = await fetch('/api/laranail/phone/audit', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ numbers: column, country: 'KE' }),
})

const { data } = await res.json()

data.summary.valid       // 1147
data.reasons.TOO_SHORT   // 49
```

Use `audit` rather than `batch` when the caller wants a verdict on the list. `batch` returns a row
per input and a ten-thousand-row request comes back as a ten-thousand-object response.

## Tighten it for a public deployment

```php
'middleware' => ['api', 'auth:sanctum'],
'throttle' => '20,1',
'max_batch' => 100,
'allow_intel' => false,
```

`allow_intel: false` refuses carrier, geocoding and timezone outright rather than relying on callers
not to ask. Each loads its own metadata, so a large batch with intel on is a different cost class.

`max_batch` is enforced with a 422 naming the field — never a silent truncation, because a caller
that sent 5,000 and got 100 back has a bug it cannot see.

## Only some of it

There is no per-endpoint switch, and adding one would be five booleans nobody sets correctly. Leave
`enabled` false and mount the pieces you want yourself — the presenter is bound in the container and
produces exactly the same JSON:

```php
use Simtabi\Laranail\Phone\Http\PhonePresenter;

Route::post('/lookup', function (Request $request, PhonePresenter $presenter) {
    $number = Phone::parse($request->string('number')->toString(), 'KE');

    return ['data' => $presenter->number($number)];
})->middleware(['auth:sanctum', 'throttle:20,1']);
```

## Behind a cached config

`config:cache` is safe: the config file holds no closures, and route registration reads it at boot.
Run `route:cache` after enabling, and again after changing `prefix` — a cached route table does not
know the prefix moved.

---

[← Docs index](../../README.md#documentation)
