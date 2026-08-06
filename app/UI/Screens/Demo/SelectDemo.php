<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Select;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Select Demo Service
 *
 * Demonstrates select component functionality:
 * - Cascading selects (country → city)
 * - Dynamic option updates
 * - Conditional enabling/disabling
 * - Single and multiple selection
 * - Searchable selects
 * - Value retrieval and display
 *
 * Uses Screen for automatic event lifecycle management.
 * Event handlers only need to modify components, no return needed.
 */
class SelectDemo extends Screen
{
    protected Select $sel_country;
    protected Select $sel_city;
    protected Select $sel_languages;
    protected Label $lbl_result;
    protected Checkbox $chk_enable_multiple;
    protected Button $btn_reset;

    /**
     * Country and city data
     */
    private const COUNTRIES = [
        ['value' => 'us', 'label' => '🇺🇸 United States'],
        ['value' => 'es', 'label' => '🇪🇸 Spain'],
        ['value' => 'fr', 'label' => '🇫🇷 France'],
        ['value' => 'jp', 'label' => '🇯🇵 Japan'],
        ['value' => 'br', 'label' => '🇧🇷 Brazil'],
    ];

    private const CITIES = [
        'us' => [
            ['value' => 'ny', 'label' => 'New York'],
            ['value' => 'la', 'label' => 'Los Angeles'],
            ['value' => 'chicago', 'label' => 'Chicago'],
            ['value' => 'miami', 'label' => 'Miami'],
        ],
        'es' => [
            ['value' => 'madrid', 'label' => 'Madrid'],
            ['value' => 'barcelona', 'label' => 'Barcelona'],
            ['value' => 'valencia', 'label' => 'Valencia'],
            ['value' => 'sevilla', 'label' => 'Sevilla'],
        ],
        'fr' => [
            ['value' => 'paris', 'label' => 'Paris'],
            ['value' => 'marseille', 'label' => 'Marseille'],
            ['value' => 'lyon', 'label' => 'Lyon'],
            ['value' => 'toulouse', 'label' => 'Toulouse'],
        ],
        'jp' => [
            ['value' => 'tokyo', 'label' => 'Tokyo'],
            ['value' => 'osaka', 'label' => 'Osaka'],
            ['value' => 'kyoto', 'label' => 'Kyoto'],
            ['value' => 'yokohama', 'label' => 'Yokohama'],
        ],
        'br' => [
            ['value' => 'sao_paulo', 'label' => 'São Paulo'],
            ['value' => 'rio', 'label' => 'Rio de Janeiro'],
            ['value' => 'brasilia', 'label' => 'Brasília'],
            ['value' => 'salvador', 'label' => 'Salvador'],
        ],
    ];

    private const CITY_INFO = [
        'ny'        => ['country' => 'United States', 'population' => '8.3M', 'timezone' => 'EST'],
        'la'        => ['country' => 'United States', 'population' => '3.9M', 'timezone' => 'PST'],
        'chicago'   => ['country' => 'United States', 'population' => '2.7M', 'timezone' => 'CST'],
        'miami'     => ['country' => 'United States', 'population' => '467K', 'timezone' => 'EST'],
        'madrid'    => ['country' => 'Spain', 'population' => '3.2M', 'timezone' => 'CET'],
        'barcelona' => ['country' => 'Spain', 'population' => '1.6M', 'timezone' => 'CET'],
        'valencia'  => ['country' => 'Spain', 'population' => '791K', 'timezone' => 'CET'],
        'sevilla'   => ['country' => 'Spain', 'population' => '688K', 'timezone' => 'CET'],
        'paris'     => ['country' => 'France', 'population' => '2.1M', 'timezone' => 'CET'],
        'marseille' => ['country' => 'France', 'population' => '870K', 'timezone' => 'CET'],
        'lyon'      => ['country' => 'France', 'population' => '513K', 'timezone' => 'CET'],
        'toulouse'  => ['country' => 'France', 'population' => '471K', 'timezone' => 'CET'],
        'tokyo'     => ['country' => 'Japan', 'population' => '13.9M', 'timezone' => 'JST'],
        'osaka'     => ['country' => 'Japan', 'population' => '2.7M', 'timezone' => 'JST'],
        'kyoto'     => ['country' => 'Japan', 'population' => '1.5M', 'timezone' => 'JST'],
        'yokohama'  => ['country' => 'Japan', 'population' => '3.7M', 'timezone' => 'JST'],
        'sao_paulo' => ['country' => 'Brazil', 'population' => '12.3M', 'timezone' => 'BRT'],
        'rio'       => ['country' => 'Brazil', 'population' => '6.7M', 'timezone' => 'BRT'],
        'brasilia'  => ['country' => 'Brazil', 'population' => '3.0M', 'timezone' => 'BRT'],
        'salvador'  => ['country' => 'Brazil', 'population' => '2.9M', 'timezone' => 'BRT'],
    ];

    private const LANGUAGES = [
        ['value' => 'en', 'label' => 'English'],
        ['value' => 'es', 'label' => 'Spanish'],
        ['value' => 'fr', 'label' => 'French'],
        ['value' => 'de', 'label' => 'German'],
        ['value' => 'it', 'label' => 'Italian'],
        ['value' => 'pt', 'label' => 'Portuguese'],
        ['value' => 'ja', 'label' => 'Japanese'],
        ['value' => 'zh', 'label' => 'Chinese'],
    ];

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->title(t('screen.demo.select_demo.title'))
            ->maxWidth(Size::px(600))
            ->centerHorizontal()
            ->shadow(2)
            ->padding(Spacing::px(30));

        // Instruction label
        $container->add(
            UI::label('lbl_instruction')
                ->text(t('screen.demo.select_demo.instruction'))
                ->style('info')
                ->width(Size::full())
        );

        // Country select
        $container->add(
            UI::select('sel_country')
                ->label(t('screen.demo.select_demo.country.label'))
                ->placeholder(t('screen.demo.select_demo.country.placeholder'))
                ->options(self::COUNTRIES)
                ->value(null)
                ->required(true)
                ->onChange('country_change')
                ->style('primary')
                ->width(Size::full())
        );

        // City select (initially disabled)
        $container->add(
            UI::select('sel_city')
                ->label(t('screen.demo.select_demo.city.label'))
                ->placeholder(t('screen.demo.select_demo.city.placeholder.select_country_first'))
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
                ->label(t('screen.demo.select_demo.languages.enable_multiple'))
                ->checked(false)
                ->onChange('toggle_multiple_languages')
                ->style('default')
                ->width(Size::full())
        );

        // Languages select (searchable and optionally multiple)
        $container->add(
            UI::select('sel_languages')
                ->label(t('screen.demo.select_demo.languages.label'))
                ->placeholder(t('screen.demo.select_demo.languages.placeholder.multiple'))
                ->options(self::LANGUAGES)
                ->value(null)
                ->searchable(true, t('screen.demo.select_demo.languages.search'))
                ->multiple(false)
                ->onChange('language_change')
                ->style('info')
                ->width(Size::full())
        );

        // Result label
        $container->add(
            UI::label('lbl_result')
                ->text(t('screen.demo.select_demo.result.initial'))
                ->style('default')
        );

        // Reset button
        $container->add(
            UI::button('btn_reset')
                ->label(t('screen.demo.select_demo.actions.reset_all'))
                ->action('reset_selections')
                ->icon('refresh')
                ->style('secondary')
                ->width(Size::full())
        );
    }

    /**
     * Handle country selection change
     * Updates city select with cities from selected country
     *
    * @param array<string, mixed> $params Contains 'value' with selected country code
     * @return void
     */
    public function onCountryChange(array $params): void
    {
        $countryCode = $params['value'] ?? null;

        if (empty($countryCode)) {
            // No country selected - disable city select
            $this->sel_city
                ->options([])
                ->value(null)
                ->disabled(true)
                ->placeholder(t('screen.demo.select_demo.city.placeholder.select_country_first'));

            $this->lbl_result
                ->text(t('screen.demo.select_demo.result.select_country_to_continue'))
                ->style('default');
        } else {
            // Country selected - enable city select with options
            $cities = self::CITIES[$countryCode] ?? [];

            $this->sel_city
                ->options($cities)
                ->value(null)
                ->disabled(false)
                ->placeholder(t('screen.demo.select_demo.city.placeholder.choose_city'));

            $countryName = collect(self::COUNTRIES)
                ->firstWhere('value', $countryCode)['label'] ?? $countryCode;

            $this->lbl_result
                ->text(t('screen.demo.select_demo.result.country_selected', ['country' => $countryName]))
                ->style('success');
        }
    }

    /**
     * Handle city selection change
     * Displays city information
     *
    * @param array<string, mixed> $params Contains 'value' with selected city code
     * @return void
     */
    public function onCityChange(array $params): void
    {
        $cityCode = $params['value'] ?? null;

        if (empty($cityCode)) {
            $this->lbl_result
                ->text(t('screen.demo.select_demo.result.select_city_to_continue'))
                ->style('default');
        } else {
            $info = self::CITY_INFO[$cityCode] ?? null;

            if ($info) {
                $cityName = collect(array_merge(...array_values(self::CITIES)))
                    ->firstWhere('value', $cityCode)['label'] ?? $cityCode;

                $text = t('screen.demo.select_demo.result.city_info.header', [
                    'city' => $cityName,
                    'country' => $info['country'],
                ]);
                $text .= t('screen.demo.select_demo.result.city_info.population', ['population' => $info['population']]);
                $text .= t('screen.demo.select_demo.result.city_info.timezone', ['timezone' => $info['timezone']]);

                $this->lbl_result
                    ->text($text)
                    ->style('success');
            } else {
                $this->lbl_result
                    ->text(t('screen.demo.select_demo.result.city_info_unavailable'))
                    ->style('warning');
            }
        }
    }

    /**
     * Handle language selection change
     * Displays selected language(s)
     *
    * @param array<string, mixed> $params Contains 'value' with selected language code(s)
     * @return void
     */
    public function onLanguageChange(array $params): void
    {
        $value = $params['value'] ?? null;

        if (empty($value)) {
            return; // Don't update result for language changes
        }

        // Get current result text using the public get() method
        $currentResult = $this->lbl_result->get('text', '');

        if (\is_array($value)) {
            // Multiple languages selected
            $languageNames = collect(self::LANGUAGES)
                ->whereIn('value', $value)
                ->pluck('label')
                ->join(', ');

            $languageText = "\n🗣️ Languages: {$languageNames}";
        } else {
            // Single language selected
            $languageName = collect(self::LANGUAGES)
                ->firstWhere('value', $value)['label'] ?? $value;

            $languageText = "\n🗣️ Language: {$languageName}";
        }

        // Only add language info if there's already city info
        if (str_contains($currentResult, '📍')) {
            $this->lbl_result->text($currentResult . $languageText);
        }
    }

    /**
     * Toggle multiple language selection mode
     *
    * @param array<string, mixed> $params Contains 'checked' boolean
     * @return void
     */
    public function onToggleMultipleLanguages(array $params): void
    {
        $enableMultiple = $params['checked'] ?? false;

        if ($enableMultiple) {
            $this->sel_languages
                ->multiple(true, 3) // Allow up to 3 selections
                ->placeholder(t('screen.demo.select_demo.languages.placeholder.up_to_three'))
                ->value([]);
        } else {
            $this->sel_languages
                ->multiple(false)
                ->placeholder(t('screen.demo.select_demo.languages.placeholder.single'))
                ->value(null);
        }
    }

    /**
     * Reset all selections
     *
    * @param array<string, mixed> $params Event parameters
     * @return void
     */
    public function onResetSelections(array $params): void
    {
        $this->sel_country->value(null);

        $this->sel_city
            ->options([])
            ->value(null)
            ->disabled(true)
            ->placeholder(t('screen.demo.select_demo.city.placeholder.select_country_first'));

        $this->sel_languages
            ->value(null)
            ->multiple(false)
            ->placeholder(t('screen.demo.select_demo.languages.placeholder.single'));

        $this->chk_enable_multiple->checked(false);

        $this->lbl_result
            ->text(t('screen.demo.select_demo.result.reset_done'))
            ->style('info');
    }
}
