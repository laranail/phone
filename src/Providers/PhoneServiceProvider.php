<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Phone\Contracts\ResolvesPhoneIntel;
use Simtabi\Laranail\Phone\CountryReconciler;
use Simtabi\Laranail\Phone\Enums\MatchLeniency;
use Simtabi\Laranail\Phone\Http\ApiRoutes;
use Simtabi\Laranail\Phone\Http\PhonePresenter;
use Simtabi\Laranail\Phone\MaskGenerator;
use Simtabi\Laranail\Phone\PhoneBatch;
use Simtabi\Laranail\Phone\PhoneCatalogue;
use Simtabi\Laranail\Phone\PhoneDialler;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneIntel;
use Simtabi\Laranail\Phone\PhoneManager;
use Simtabi\Laranail\Phone\PhoneNormalizer;
use Simtabi\Laranail\Phone\PhoneNumberFactory;
use Simtabi\Laranail\Phone\PhoneScanner;
use Simtabi\Laranail\Phone\ShortNumbers;

/**
 * Wires `laranail/phone` onto the house {@see PackageServiceProvider}.
 *
 * Every binding lives in {@see packageBooted()} rather than `registeringPackage()`, because all of
 * them read config and the package's config merge has not run at register time. Getting this wrong
 * produces a package that works in development and silently uses defaults once `config:cache` runs.
 */
final class PhoneServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/phone')
            ->hasConfigFile()
            ->hasTranslations('laranail-phone');
    }

    public function packageBooted(): void
    {
        $this->bindNormalizer();
        $this->bindIntel();
        $this->bindFormatter();
        $this->bindDerived();
        $this->bindHttp();
        $this->registerBlueprintMacro();
        ApiRoutes::register($this->app->make(Repository::class));
    }

    private function bindNormalizer(): void
    {
        $this->app->singleton(PhoneNormalizer::class, fn (): PhoneNormalizer => new PhoneNormalizer(
            convertVanityLetters: config('laranail.phone.convert_vanity_letters', false) === true,
        ));
    }

    private function bindIntel(): void
    {
        // Bound to the interface, so an application that wants to answer carrier lookups from its own
        // HLR service can swap the implementation without touching the value object.
        $this->app->singleton(ResolvesPhoneIntel::class, fn (): PhoneIntel => new PhoneIntel(
            fallbackLocale: $this->string(config('laranail.phone.intel.locale')) ?? app()->getLocale(),
        ));
    }

    private function bindFormatter(): void
    {
        $this->app->singleton(PhoneFormatter::class, fn (Application $app): PhoneFormatter => new PhoneFormatter(
            normalizer: $app->make(PhoneNormalizer::class),
            intel: config('laranail.phone.intel.enabled', true) === true
                ? $app->make(ResolvesPhoneIntel::class)
                : null,
            defaultCountry: $this->string(config('laranail.phone.default_country')),
        ));
    }

    private function bindDerived(): void
    {
        $this->app->singleton(MaskGenerator::class, fn (Application $app): MaskGenerator => new MaskGenerator(
            formatter: $app->make(PhoneFormatter::class),
        ));

        $this->app->singleton(CountryReconciler::class, fn (Application $app): CountryReconciler => new CountryReconciler(
            formatter: $app->make(PhoneFormatter::class),
        ));

        $this->app->singleton(PhoneNumberFactory::class, fn (Application $app): PhoneNumberFactory => new PhoneNumberFactory(
            formatter: $app->make(PhoneFormatter::class),
            defaultCountry: $this->string(config('laranail.phone.default_country')) ?? 'US',
        ));

        $this->app->singleton(PhoneDialler::class, fn (Application $app): PhoneDialler => new PhoneDialler(
            formatter: $app->make(PhoneFormatter::class),
        ));

        // Stateless lookups over libphonenumber's own metadata. Singletons because the underlying
        // metadata loading is what costs, and it is per-region and cached inside the library.
        $this->app->singleton(PhoneCatalogue::class, fn (): PhoneCatalogue => new PhoneCatalogue);
        $this->app->singleton(ShortNumbers::class, fn (): ShortNumbers => new ShortNumbers);

        $this->app->singleton(PhoneScanner::class, fn (): PhoneScanner => new PhoneScanner(
            defaultCountry: $this->string(config('laranail.phone.default_country')),
            leniency: MatchLeniency::tryFrom($this->string(config('laranail.phone.scanning.leniency')) ?? 'VALID')
                ?? MatchLeniency::Valid,
            limit: $this->int(config('laranail.phone.scanning.limit')) ?? PHP_INT_MAX,
        ));

        // Stateless over the formatter, so a singleton. Its memoisation is per-call and discarded
        // with the pass — keying arbitrary user input for the lifetime of a worker would be an
        // unbounded map, and the locality that makes it pay off is inside one list anyway.
        $this->app->singleton(PhoneBatch::class, fn (Application $app): PhoneBatch => new PhoneBatch(
            formatter: $app->make(PhoneFormatter::class),
        ));

        // The facade's accessor. Forwards the original five methods unchanged, so the surface is a
        // superset of what it was.
        $this->app->singleton(PhoneManager::class, fn (Application $app): PhoneManager => new PhoneManager(
            formatter: $app->make(PhoneFormatter::class),
            dialler: $app->make(PhoneDialler::class),
            scanner: $app->make(PhoneScanner::class),
            catalogue: $app->make(PhoneCatalogue::class),
            shortNumbers: $app->make(ShortNumbers::class),
            batch: $app->make(PhoneBatch::class),
        ));
    }

    /**
     * The HTTP layer's own collaborators.
     *
     * Bound unconditionally even when the API is disabled, because they cost nothing until something
     * resolves them and an application may want the presenter for its own controller — the wire
     * format is useful whether or not this package owns the route.
     */
    private function bindHttp(): void
    {
        $this->app->singleton(PhonePresenter::class, fn (Application $app): PhonePresenter => new PhonePresenter(
            formatter: $app->make(PhoneFormatter::class),
            masks: $app->make(MaskGenerator::class),
            catalogue: $app->make(PhoneCatalogue::class),
        ));
    }

    /**
     * A config value as a string, or null.
     *
     * `config()` returns `mixed` and a cast would paper over that rather than answer it: a
     * `default_country` set to an array or a stray `true` in `.env` should fall back to the default,
     * not become the string `"1"` and quietly parse every number against nothing.
     */
    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * `$table->phoneNumber('phone')` — the number column, its country column and an index.
     *
     * The pair exists because E.164 alone cannot always name a country: `+1` is shared by twenty-odd
     * NANP members. The number column stays the source of truth and the country column is a derived
     * convenience, so dropping the second never loses information.
     *
     * 20 characters is deliberate: E.164 caps a number at 15 digits, plus the `+`, leaving room for a
     * short `;ext=` suffix without reaching for a `text` column.
     */
    private function registerBlueprintMacro(): void
    {
        if (Blueprint::hasMacro('phoneNumber')) {
            return;
        }

        Blueprint::macro('phoneNumber', function (string $column = 'phone', bool $nullable = true, bool $index = true): void {
            /** @var Blueprint $this */
            $this->string($column, 20)->nullable($nullable);
            $this->char($column . '_country', 2)->nullable();

            if ($index) {
                $this->index($column);
            }
        });
    }
}
