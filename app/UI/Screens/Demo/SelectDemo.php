<?php

namespace App\UI\Screens\Demo;

use App\Contracts\CityServiceInterface;
use App\Contracts\CountryServiceInterface;
use App\Contracts\LanguageServiceInterface;
use App\Contracts\StateServiceInterface;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Select;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Select Demo Screen
 *
 * Demonstrates modern select component functionality with a 3-level cascade:
 * - Country → State/Province → City
 * - Asynchronous data fetching delegated to domain services
 * - Cascading state and options updates via POST /api/ui-event
 * - Searchable selects and single/multiple selection mode
 */
class SelectDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected CountryServiceInterface $countryService;

    protected StateServiceInterface $stateService;

    protected CityServiceInterface $cityService;

    protected LanguageServiceInterface $languageService;

    protected Select $sel_country;

    protected Select $sel_state;

    protected Select $sel_city;

    protected Select $sel_languages;

    protected Label $lbl_result;

    protected Checkbox $chk_enable_multiple;

    protected Button $btn_reset;

    // Persisted state variables across event requests
    protected string $store_selected_country = '';

    protected string $store_selected_state = '';

    public function __construct(
        ?CountryServiceInterface $countryService = null,
        ?StateServiceInterface $stateService = null,
        ?CityServiceInterface $cityService = null,
        ?LanguageServiceInterface $languageService = null,
    ) {
        parent::__construct();

        $this->countryService = $countryService ?? app(CountryServiceInterface::class);
        $this->stateService = $stateService ?? app(StateServiceInterface::class);
        $this->cityService = $cityService ?? app(CityServiceInterface::class);
        $this->languageService = $languageService ?? app(LanguageServiceInterface::class);
    }

    /**
     * Build base UI layout and declare components
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->card()
            ->maxWidth(Size::px(600))
            ->centerHorizontal()
            ->padding(Spacing::px(10));

        // Instruction label
        $container->add(
            UI::label('lbl_instruction')
                ->text(t('screens.demo.select_demo.instruction'))
                ->style('info')
                ->width(Size::full())
        );

        // 1. Country select (asynchronously populated via GET /api/ui-data?action=countries)
        $container->add(
            UI::select('sel_country')
                ->label(t('screens.demo.select_demo.country.label'))
                ->placeholder(t('screens.demo.select_demo.country.placeholder'))
                ->action('countries')
                ->value(null)
                ->required(true)
                ->onChange('country_change')
                ->style('primary')
                ->width(Size::full())
        );

        // 2. State/Province select (initially disabled, loaded dynamically when country is chosen)
        $container->add(
            UI::select('sel_state')
                ->label('State / Province')
                ->placeholder('Select country first...')
                ->options([])
                ->value(null)
                ->disabled(true)
                ->onChange('state_change')
                ->style('primary')
                ->width(Size::full())
        );

        // 3. City select (initially disabled, loaded dynamically when state/province is chosen)
        $container->add(
            UI::select('sel_city')
                ->label(t('screens.demo.select_demo.city.label'))
                ->placeholder('Select state/province first...')
                ->options([])
                ->value(null)
                ->disabled(true)
                ->onChange('city_change')
                ->style('primary')
                ->width(Size::full())
        );

        // Checkbox to enable multiple language selection
        $container->add(
            UI::checkbox('chk_enable_multiple')
                ->label(t('screens.demo.select_demo.languages.enable_multiple'))
                ->checked(false)
                ->onChange('toggle_multiple_languages')
                ->style('default')
                ->width(Size::full())
        );

        // Languages select (asynchronously populated via GET /api/ui-data?action=languages)
        $container->add(
            UI::select('sel_languages')
                ->label(t('screens.demo.select_demo.languages.label'))
                ->placeholder(t('screens.demo.select_demo.languages.placeholder.multiple'))
                ->action('languages')
                ->value(null)
                ->searchable(true, t('screens.demo.select_demo.languages.search'))
                ->multiple(false)
                ->onChange('language_change')
                ->style('info')
                ->width(Size::full())
        );

        // Result label
        $container->add(
            UI::label('lbl_result')
                ->text(t('screens.demo.select_demo.result.initial'))
                ->style('default')
        );

        // Reset button
        $container->add(
            UI::button('btn_reset')
                ->label(t('screens.demo.select_demo.actions.reset_all'))
                ->action('reset_selections')
                ->icon('refresh')
                ->style('secondary')
                ->width(Size::full())
        );
    }

    /**
     * Query handler: fetch countries from country service (GET /api/ui-data?action=countries)
     *
     * @param  array<string, mixed>  $params
     * @return list<array{value: string, label: string}>
     */
    public function getCountries(array $params = []): array
    {
        return $this->countryService->getCountries();
    }

    /**
     * Query handler: fetch states/provinces for a given country (GET /api/ui-data?action=states)
     *
     * @param  array<string, mixed>  $params
     * @return list<array{value: string, label: string}>
     */
    public function getStates(array $params = []): array
    {
        $rawCountryCode = $params['country'] ?? $this->store_selected_country;
        $countryCode = is_string($rawCountryCode) ? $rawCountryCode : '';

        return $this->stateService->getStates($countryCode);
    }

    /**
     * Query handler: fetch cities for a country and state (GET /api/ui-data?action=cities)
     *
     * @param  array<string, mixed>  $params
     * @return list<array{value: string, label: string}>
     */
    public function getCities(array $params = []): array
    {
        $rawCountryCode = $params['country'] ?? $this->store_selected_country;
        $countryCode = is_string($rawCountryCode) ? $rawCountryCode : '';

        $rawState = $params['state'] ?? $this->store_selected_state;
        $stateName = is_string($rawState) ? $rawState : '';

        return $this->cityService->getCities($countryCode, $stateName);
    }

    /**
     * Query handler: fetch languages from language service (GET /api/ui-data?action=languages)
     *
     * @param  array<string, mixed>  $params
     * @return list<array{value: string, label: string}>
     */
    public function getLanguages(array $params = []): array
    {
        return $this->languageService->getLanguages();
    }

    /**
     * Handle country selection change (POST /api/ui-event)
     *
     * @param  array<string, mixed>  $params
     */
    public function onCountryChange(array $params): void
    {
        $rawCountryCode = $params['value'] ?? null;
        $countryCode = is_string($rawCountryCode) ? strtolower($rawCountryCode) : '';

        $this->store_selected_country = $countryCode;
        $this->store_selected_state = '';

        if (empty($countryCode)) {
            $this->sel_state
                ->options([])
                ->value(null)
                ->disabled(true)
                ->placeholder('Select country first...');

            $this->sel_city
                ->options([])
                ->value(null)
                ->disabled(true)
                ->placeholder('Select state/province first...');

            $this->lbl_result
                ->text(t('screens.demo.select_demo.result.select_country_to_continue'))
                ->style('default');
        } else {
            $states = $this->getStates(['country' => $countryCode]);

            $this->sel_state
                ->options($states)
                ->value(null)
                ->disabled(false)
                ->placeholder('Choose a state / province...');

            $this->sel_city
                ->options([])
                ->value(null)
                ->disabled(true)
                ->placeholder('Select state/province first...');

            $this->lbl_result
                ->text('🌎 País: '.strtoupper($countryCode).'. Por favor selecciona un estado/provincia.')
                ->style('info');
        }
    }

    /**
     * Handle state selection change (POST /api/ui-event)
     *
     * @param  array<string, mixed>  $params
     */
    public function onStateChange(array $params): void
    {
        $rawState = $params['value'] ?? null;
        $state = is_string($rawState) ? $rawState : '';

        $this->store_selected_state = $state;
        $countryCode = $this->store_selected_country;

        if (empty($state)) {
            $this->sel_city
                ->options([])
                ->value(null)
                ->disabled(true)
                ->placeholder('Select state/province first...');

            $this->lbl_result
                ->text('🌎 País: '.strtoupper($countryCode).'. Selecciona un estado/provincia.')
                ->style('default');
        } else {
            $cities = $this->getCities([
                'country' => $countryCode,
                'state' => $state,
            ]);

            $this->sel_city
                ->options($cities)
                ->value(null)
                ->disabled(false)
                ->placeholder('Choose a city...');

            $this->lbl_result
                ->text('🌎 País: '.strtoupper($countryCode)." | 🏛️ Estado/Prov: {$state}. Por favor selecciona una ciudad.")
                ->style('info');
        }
    }

    /**
     * Handle city selection change (POST /api/ui-event)
     *
     * @param  array<string, mixed>  $params
     */
    public function onCityChange(array $params): void
    {
        $rawCityCode = $params['value'] ?? null;
        $city = is_string($rawCityCode) ? $rawCityCode : '';

        if (empty($city)) {
            $this->lbl_result
                ->text('Selecciona una ciudad para continuar.')
                ->style('default');
        } else {
            $country = strtoupper($this->store_selected_country);
            $state = $this->store_selected_state;

            $text = "📍 Ubicación completa seleccionada:\n";
            $text .= "• País: {$country}\n";
            if ($state !== '') {
                $text .= "• Estado/Provincia: {$state}\n";
            }
            $text .= "• Ciudad: {$city}";

            $this->lbl_result
                ->text($text)
                ->style('success');
        }
    }

    /**
     * Handle language selection change (POST /api/ui-event)
     *
     * @param  array<string, mixed>  $params
     */
    public function onLanguageChange(array $params): void
    {
        $value = $params['value'] ?? null;

        if (empty($value)) {
            return;
        }

        $currentResultRaw = $this->lbl_result->get('text', '');
        $currentResult = is_string($currentResultRaw) ? $currentResultRaw : '';

        if (\is_array($value)) {
            $cleanLangs = array_values(array_filter($value, static fn (mixed $item): bool => is_string($item)));
            $languageText = "\n🗣️ Idiomas: ".implode(', ', array_map('strtoupper', $cleanLangs));
        } else {
            $languageText = "\n🗣️ Idioma: ".strtoupper(is_string($value) || is_numeric($value) ? (string) $value : '');
        }

        if (str_contains($currentResult, '📍') || str_contains($currentResult, 'País') || str_contains($currentResult, 'Ciudad')) {
            $this->lbl_result->text($currentResult.$languageText);
        } else {
            $this->lbl_result->text(trim($languageText))->style('info');
        }
    }

    /**
     * Toggle multiple language selection mode
     *
     * @param  array<string, mixed>  $params
     */
    public function onToggleMultipleLanguages(array $params): void
    {
        $enableMultiple = (bool) ($params['checked'] ?? false);

        if ($enableMultiple) {
            $this->sel_languages
                ->multiple(true, 3)
                ->placeholder(t('screens.demo.select_demo.languages.placeholder.up_to_three'))
                ->value([]);
        } else {
            $this->sel_languages
                ->multiple(false)
                ->placeholder(t('screens.demo.select_demo.languages.placeholder.single'))
                ->value(null);
        }
    }

    /**
     * Reset all selections
     *
     * @param  array<string, mixed>  $params
     */
    public function onResetSelections(array $params): void
    {
        $this->store_selected_country = '';
        $this->store_selected_state = '';

        $this->sel_country->value(null);

        $this->sel_state
            ->options([])
            ->value(null)
            ->disabled(true)
            ->placeholder('Select country first...');

        $this->sel_city
            ->options([])
            ->value(null)
            ->disabled(true)
            ->placeholder('Select state/province first...');

        $this->sel_languages
            ->value(null)
            ->multiple(false)
            ->placeholder(t('screens.demo.select_demo.languages.placeholder.single'));

        $this->chk_enable_multiple->checked(false);

        $this->lbl_result
            ->text(t('screens.demo.select_demo.result.reset_done'))
            ->style('info');
    }
}
