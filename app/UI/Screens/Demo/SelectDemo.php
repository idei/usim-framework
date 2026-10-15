<?php

namespace App\UI\Screens\Demo;

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
use Illuminate\Support\Facades\Http;

/**
 * Select Demo Service
 *
 * Demonstrates modern select component functionality with a 3-level cascade:
 * - Country → State/Province → City
 * - Asynchronous data fetching from public APIs via GET /api/ui-data
 * - Cascading state and options updates via POST /api/ui-event
 * - Searchable selects and single/multiple selection mode
 * - Robust fallback data for offline / restricted environments
 */
class SelectDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

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
     * Query handler: fetch countries from public API (GET /api/ui-data?action=countries)
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array{value: string, label: string}>
     */
    public function getCountries(array $params = []): array
    {
        try {
            $response = Http::timeout(3)->get('https://restcountries.com/v3.1/all?fields=cca2,name,flag');
            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && ! empty($data)) {
                    $countries = [];
                    foreach ($data as $item) {
                        $code = strtolower((string) ($item['cca2'] ?? ''));
                        $name = (string) ($item['name']['common'] ?? $code);
                        $flag = (string) ($item['flag'] ?? '');
                        if ($code !== '' && $name !== '') {
                            $countries[] = [
                                'value' => $code,
                                'label' => trim("{$flag} {$name}"),
                            ];
                        }
                    }
                    if (! empty($countries)) {
                        usort($countries, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
                        return $countries;
                    }
                }
            }
        } catch (\Throwable) {
            // Fallback gracefully below
        }

        return [
            ['value' => 'us', 'label' => '🇺🇸 United States'],
            ['value' => 'es', 'label' => '🇪🇸 Spain'],
            ['value' => 'ar', 'label' => '🇦🇷 Argentina'],
            ['value' => 'mx', 'label' => '🇲🇽 Mexico'],
            ['value' => 'fr', 'label' => '🇫🇷 France'],
            ['value' => 'de', 'label' => '🇩🇪 Germany'],
            ['value' => 'it', 'label' => '🇮🇹 Italy'],
            ['value' => 'br', 'label' => '🇧🇷 Brazil'],
            ['value' => 'ca', 'label' => '🇨🇦 Canada'],
            ['value' => 'jp', 'label' => '🇯🇵 Japan'],
        ];
    }

    /**
     * Query handler: fetch states/provinces for a given country (GET /api/ui-data?action=states)
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array{value: string, label: string}>
     */
    public function getStates(array $params = []): array
    {
        $countryCode = strtolower((string) ($params['country'] ?? $this->store_selected_country));

        if ($countryCode === '') {
            return [];
        }

        $countryMap = [
            'us' => 'United States',
            'es' => 'Spain',
            'ar' => 'Argentina',
            'mx' => 'Mexico',
            'fr' => 'France',
            'de' => 'Germany',
            'it' => 'Italy',
            'br' => 'Brazil',
            'ca' => 'Canada',
            'jp' => 'Japan',
        ];

        $countryName = $countryMap[$countryCode] ?? $countryCode;

        try {
            $response = Http::timeout(3)->post('https://countriesnow.space/api/v0.1/countries/states', [
                'country' => $countryName,
            ]);

            if ($response->successful()) {
                $states = $response->json('data.states');
                if (is_array($states) && ! empty($states)) {
                    $options = [];
                    foreach ($states as $state) {
                        $name = (string) ($state['name'] ?? '');
                        if ($name !== '') {
                            $options[] = [
                                'value' => $name,
                                'label' => $name,
                            ];
                        }
                    }
                    if (! empty($options)) {
                        return $options;
                    }
                }
            }
        } catch (\Throwable) {
            // Fallback gracefully below
        }

        $fallbacks = [
            'us' => ['California', 'New York', 'Texas', 'Florida', 'Illinois'],
            'es' => ['Madrid', 'Cataluña', 'Andalucía', 'Comunidad Valenciana', 'Galicia'],
            'ar' => ['Buenos Aires', 'Córdoba', 'Santa Fe', 'Mendoza', 'San Juan'],
            'mx' => ['Ciudad de México', 'Jalisco', 'Nuevo León', 'Puebla', 'Yucatán'],
            'fr' => ['Île-de-France', 'Auvergne-Rhône-Alpes', 'Provence-Alpes-Côte d\'Azur', 'Occitanie'],
            'de' => ['Bavaria', 'Berlin', 'Hamburg', 'North Rhine-Westphalia', 'Hesse'],
            'it' => ['Lombardy', 'Lazio', 'Campania', 'Veneto', 'Tuscany'],
            'br' => ['São Paulo', 'Rio de Janeiro', 'Bahia', 'Minas Gerais', 'Paraná'],
            'ca' => ['Ontario', 'Quebec', 'British Columbia', 'Alberta'],
            'jp' => ['Tokyo', 'Osaka', 'Kanagawa', 'Aichi', 'Hokkaido'],
        ];

        $stateList = $fallbacks[$countryCode] ?? ['Region 1', 'Region 2', 'Region 3'];
        return array_map(static fn (string $s): array => [
            'value' => $s,
            'label' => $s,
        ], $stateList);
    }

    /**
     * Query handler: fetch cities for a country and state (GET /api/ui-data?action=cities)
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array{value: string, label: string}>
     */
    public function getCities(array $params = []): array
    {
        $countryCode = strtolower((string) ($params['country'] ?? $this->store_selected_country));
        $stateName = (string) ($params['state'] ?? $this->store_selected_state);

        if ($countryCode === '') {
            return [];
        }

        $countryMap = [
            'us' => 'United States',
            'es' => 'Spain',
            'ar' => 'Argentina',
            'mx' => 'Mexico',
            'fr' => 'France',
            'de' => 'Germany',
            'it' => 'Italy',
            'br' => 'Brazil',
            'ca' => 'Canada',
            'jp' => 'Japan',
        ];

        $countryName = $countryMap[$countryCode] ?? $countryCode;

        try {
            $response = Http::timeout(3)->post('https://countriesnow.space/api/v0.1/countries/state/cities', [
                'country' => $countryName,
                'state' => $stateName,
            ]);

            if ($response->successful()) {
                $citiesData = $response->json('data');
                if (is_array($citiesData) && ! empty($citiesData)) {
                    $options = [];
                    foreach (array_slice($citiesData, 0, 30) as $city) {
                        $cityStr = (string) $city;
                        $options[] = [
                            'value' => $cityStr,
                            'label' => $cityStr,
                        ];
                    }
                    if (! empty($options)) {
                        return $options;
                    }
                }
            }
        } catch (\Throwable) {
            // Fallback gracefully below
        }

        $cityFallbacks = [
            'california' => ['Los Angeles', 'San Francisco', 'San Diego', 'San Jose', 'Sacramento'],
            'new york' => ['New York City', 'Buffalo', 'Rochester', 'Albany', 'Syracuse'],
            'texas' => ['Houston', 'Austin', 'Dallas', 'San Antonio', 'Fort Worth'],
            'florida' => ['Miami', 'Orlando', 'Tampa', 'Jacksonville', 'Tallahassee'],
            'illinois' => ['Chicago', 'Aurora', 'Naperville', 'Joliet', 'Springfield'],
            'madrid' => ['Madrid', 'Móstoles', 'Alcalá de Henares', 'Fuenlabrada', 'Leganés'],
            'cataluña' => ['Barcelona', 'L\'Hospitalet', 'Badalona', 'Terrassa', 'Sabadell'],
            'andalucía' => ['Sevilla', 'Málaga', 'Córdoba', 'Granada', 'Cádiz'],
            'buenos aires' => ['La Plata', 'Mar del Plata', 'Bahía Blanca', 'Tandil'],
            'córdoba' => ['Córdoba', 'Villa Carlos Paz', 'Río Cuarto', 'San Francisco'],
            'santa fe' => ['Rosario', 'Santa Fe', 'Rafaela', 'Venado Tuerto'],
            'mendoza' => ['Mendoza', 'San Rafael', 'Godoy Cruz', 'Luján de Cuyo'],
            'san juan' => ['San Juan', 'Rawson', 'Rivadavia', 'Chimbas', 'Pocito'],
            'jalisco' => ['Guadalajara', 'Zapopan', 'Puerto Vallarta', 'Tlaquepaque'],
            'nuevo león' => ['Monterrey', 'San Pedro Garza García', 'Guadalupe', 'Apodaca'],
            'ciudad de méxico' => ['Cuauhtémoc', 'Benito Juárez', 'Coyoacán', 'Miguel Hidalgo'],
        ];

        $normalizedState = strtolower($stateName);
        $cities = $cityFallbacks[$normalizedState] ?? ['Capital City', 'Central City', 'Coastal City'];

        return array_map(static fn (string $c): array => [
            'value' => $c,
            'label' => $c,
        ], $cities);
    }

    /**
     * Query handler: fetch languages from public API (GET /api/ui-data?action=languages)
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array{value: string, label: string}>
     */
    public function getLanguages(array $params = []): array
    {
        try {
            $response = Http::timeout(3)->get('https://restcountries.com/v3.1/all?fields=languages');
            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && ! empty($data)) {
                    $uniqueLanguages = [];
                    foreach ($data as $item) {
                        $langs = $item['languages'] ?? [];
                        if (is_array($langs)) {
                            foreach ($langs as $code => $name) {
                                $uniqueLanguages[$code] = $name;
                            }
                        }
                    }
                    if (! empty($uniqueLanguages)) {
                        asort($uniqueLanguages);
                        $result = [];
                        foreach ($uniqueLanguages as $code => $name) {
                            $result[] = [
                                'value' => (string) $code,
                                'label' => (string) $name,
                            ];
                        }
                        if (! empty($result)) {
                            return $result;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Fallback gracefully below
        }

        return [
            ['value' => 'en', 'label' => 'English'],
            ['value' => 'es', 'label' => 'Spanish'],
            ['value' => 'fr', 'label' => 'French'],
            ['value' => 'de', 'label' => 'German'],
            ['value' => 'it', 'label' => 'Italian'],
            ['value' => 'pt', 'label' => 'Portuguese'],
            ['value' => 'ja', 'label' => 'Japanese'],
            ['value' => 'zh', 'label' => 'Chinese'],
            ['value' => 'ru', 'label' => 'Russian'],
            ['value' => 'ar', 'label' => 'Arabic'],
        ];
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
                ->text("🌎 País: " . strtoupper($countryCode) . ". Por favor selecciona un estado/provincia.")
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
                ->text("🌎 País: " . strtoupper($countryCode) . ". Selecciona un estado/provincia.")
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
                ->text("🌎 País: " . strtoupper($countryCode) . " | 🏛️ Estado/Prov: {$state}. Por favor selecciona una ciudad.")
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
                ->text("Selecciona una ciudad para continuar.")
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
            $languageText = "\n🗣️ Idiomas: " . implode(', ', array_map('strtoupper', $cleanLangs));
        } else {
            $languageText = "\n🗣️ Idioma: " . strtoupper((string) $value);
        }

        if (str_contains($currentResult, '📍') || str_contains($currentResult, 'País') || str_contains($currentResult, 'Ciudad')) {
            $this->lbl_result->text($currentResult . $languageText);
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
