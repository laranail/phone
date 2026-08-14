<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Providers;

use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Phone\Contracts\ResolvesPhoneIntel;
use Simtabi\Laranail\Phone\CountryReconciler;
use Simtabi\Laranail\Phone\MaskGenerator;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneIntel;
use Simtabi\Laranail\Phone\PhoneNormalizer;
use Simtabi\Laranail\Phone\PhoneNumberFactory;

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
        $this->registerBlueprintMacro();
    }

    private function bindNormalizer(): void
    {
        $this->app->singleton(PhoneNormalizer::class, fn (): PhoneNormalizer => new PhoneNormalizer(
            convertVanityLetters: (bool) config('laranail.phone.convert_vanity_letters', false),
        ));
    }

    private function bindIntel(): void
    {
        // Bound to the interface, so an application that wants to answer carrier lookups from its own
        // HLR service can swap the implementation without touching the value object.
        $this->app->singleton(ResolvesPhoneIntel::class, fn (): PhoneIntel => new PhoneIntel(
            fallbackLocale: (string) (config('laranail.phone.intel.locale') ?? app()->getLocale()),
        ));
    }

    private function bindFormatter(): void
    {
        $this->app->singleton(PhoneFormatter::class, fn ($app): PhoneFormatter => new PhoneFormatter(
            normalizer: $app->make(PhoneNormalizer::class),
            intel: config('laranail.phone.intel.enabled', true) === true
                ? $app->make(ResolvesPhoneIntel::class)
                : null,
            defaultCountry: config('laranail.phone.default_country'),
        ));
    }

    private function bindDerived(): void
    {
        $this->app->singleton(MaskGenerator::class, fn ($app): MaskGenerator => new MaskGenerator(
            formatter: $app->make(PhoneFormatter::class),
        ));

        $this->app->singleton(CountryReconciler::class, fn ($app): CountryReconciler => new CountryReconciler(
            formatter: $app->make(PhoneFormatter::class),
        ));

        $this->app->singleton(PhoneNumberFactory::class, fn ($app): PhoneNumberFactory => new PhoneNumberFactory(
            formatter: $app->make(PhoneFormatter::class),
            defaultCountry: (string) (config('laranail.phone.default_country') ?? 'US'),
        ));
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
