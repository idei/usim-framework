<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\UI;
use Idei\Usim\Screen;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Select;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AddressForm extends Screen
{
    private const COUNTRIES_URL = 'https://countriesnow.space/api/v0.1/countries/positions';
    private const STATES_URL = 'https://countriesnow.space/api/v0.1/countries/states/q';

    protected Input $input_address;
    protected Select $input_country;
    protected Select $input_province;
    protected Input $input_street_number;
    protected Input $input_postal_code;
    protected Button $btn_submit;
    protected Label $lbl_result;
    protected ?string $store_selected_country = null;
    protected ?string $store_selected_province = null;

    public static function getMenuLabel(): string
    {
        return 'Formulario de direccion';
    }

    public static function getMenuIcon(): ?string
    {
        return '📍';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $countryOptions = $this->fetchCountryOptions();
        $provinceOptions = $this->store_selected_country
            ? $this->fetchProvinceOptions($this->store_selected_country)
            : [];

        $container
            ->title('Solicitud de direccion')
            ->maxWidth('640px')
            ->centerHorizontal()
            ->shadow(2)
            ->padding('30px');

        $container->add(
            UI::label('lbl_instruction')
                ->text('Completa los datos de la direccion solicitada.')
                ->style('info')
                ->width('100%')
        );

        $container->add(
            UI::input('input_address')
                ->label('Direccion')
                ->placeholder('Ej. Casa principal o direccion de entrega')
                ->value('')
                ->required(true)
                ->type('text')
                ->width('100%')
        );

        $container->add(
            UI::select('input_country')
                ->label('Pais')
                ->placeholder('Selecciona un pais')
                ->options($countryOptions)
                ->value($this->store_selected_country)
                ->required(true)
                ->searchable(true, 'Buscar pais')
                ->onChange('country_change')
                ->clearable(true)
                ->width('100%')
        );

        $container->add(
            UI::select('input_province')
                ->label('Provincia o estado')
                ->placeholder($this->store_selected_country ? 'Selecciona una provincia o estado' : 'Selecciona primero un pais')
                ->options($provinceOptions)
                ->value($this->store_selected_province)
                ->required(true)
                ->searchable(true, 'Buscar provincia o estado')
                ->disabled($this->store_selected_country === null)
                ->clearable(true)
                ->width('100%')
        );

        $container->add(
            UI::input('input_street_number')
                ->label('Calle y numero')
                ->placeholder('Ej. Av. Siempre Viva 742')
                ->value('')
                ->required(true)
                ->type('text')
                ->width('100%')
        );

        $container->add(
            UI::input('input_postal_code')
                ->label('Codigo postal')
                ->placeholder('Ej. 1000')
                ->value('')
                ->required(true)
                ->type('text')
                ->width('100%')
        );

        $container->add(
            UI::button('btn_submit')
                ->label('Enviar solicitud')
                ->action('submit_address_form')
                ->style('primary')
        );

        $container->add(
            UI::label('lbl_result')
                ->text('Completa el formulario para enviar la direccion.')
                ->style('secondary')
                ->width('100%')
        );
    }

    protected function postLoadUI(): void
    {
        $countryOptions = $this->fetchCountryOptions();
        $provinceOptions = $this->store_selected_country
            ? $this->fetchProvinceOptions($this->store_selected_country)
            : [];

        $this->input_address->error(null);
        $this->input_country
            ->options($countryOptions)
            ->value($this->store_selected_country)
            ->errorMessage('');
        $this->input_province
            ->options($provinceOptions)
            ->value($this->store_selected_province)
            ->disabled($this->store_selected_country === null)
            ->placeholder($this->store_selected_country ? 'Selecciona una provincia o estado' : 'Selecciona primero un pais')
            ->errorMessage('');
        $this->input_street_number->error(null);
        $this->input_postal_code->error(null);
        $this->lbl_result
            ->text('Completa el formulario para enviar la direccion.')
            ->style('secondary');
    }

    public function onCountryChange(array $params): void
    {
        $country = trim((string) ($params['value'] ?? ''));

        $this->store_selected_country = $country !== '' ? $country : null;
        $this->store_selected_province = null;
        $this->input_country->errorMessage('');
        $this->input_province->errorMessage('');

        if ($this->store_selected_country === null) {
            $this->input_province
                ->options([])
                ->value(null)
                ->disabled(true)
                ->placeholder('Selecciona primero un pais');

            $this->lbl_result
                ->text('Selecciona un pais para cargar sus provincias o estados.')
                ->style('secondary');

            return;
        }

        $provinceOptions = $this->fetchProvinceOptions($this->store_selected_country);

        $this->input_province
            ->options($provinceOptions)
            ->value(null)
            ->disabled($provinceOptions === [])
            ->placeholder($provinceOptions === []
                ? 'No hay provincias o estados disponibles'
                : 'Selecciona una provincia o estado');

        $this->lbl_result
            ->text($provinceOptions === []
                ? sprintf('No se encontraron provincias o estados para %s.', $this->store_selected_country)
                : sprintf('Selecciona una provincia o estado para %s.', $this->store_selected_country))
            ->style($provinceOptions === [] ? 'warning' : 'info');
    }

    public function onSubmitAddressForm(array $params): void
    {
        $address = trim($params['input_address'] ?? '');
        $country = trim($params['input_country'] ?? '');
        $province = trim($params['input_province'] ?? '');
        $streetNumber = trim($params['input_street_number'] ?? '');
        $postalCode = trim($params['input_postal_code'] ?? '');

        $this->input_address->error(null);
        $this->input_country->errorMessage('');
        $this->input_province->errorMessage('');
        $this->input_street_number->error(null);
        $this->input_postal_code->error(null);

        $hasErrors = false;

        if ($address === '') {
            $this->input_address->error('La direccion es obligatoria.');
            $hasErrors = true;
        }

        if ($country === '') {
            $this->input_country->errorMessage('El pais es obligatorio.');
            $hasErrors = true;
        }

        if ($province === '') {
            $this->input_province->errorMessage('La provincia o estado es obligatoria.');
            $hasErrors = true;
        }

        if ($streetNumber === '') {
            $this->input_street_number->error('La calle y numero son obligatorios.');
            $hasErrors = true;
        }

        if ($postalCode === '') {
            $this->input_postal_code->error('El codigo postal es obligatorio.');
            $hasErrors = true;
        } elseif (!preg_match('/^[A-Za-z0-9\-\s]{3,12}$/', $postalCode)) {
            $this->input_postal_code->error('Ingresa un codigo postal valido.');
            $hasErrors = true;
        }

        if ($hasErrors) {
            $this->lbl_result
                ->text('Hay errores en el formulario. Revisa los campos marcados.')
                ->style('danger');
            $this->toast('Corrige los errores del formulario.', 'error');
            return;
        }

        $this->lbl_result
            ->text(sprintf(
                'Solicitud enviada para %s, %s, %s, %s, CP %s.',
                $address,
                $country,
                $province,
                $streetNumber,
                strtoupper($postalCode)
            ))
            ->style('success');

        $this->toast('Solicitud de direccion enviada correctamente.', 'success');

        $this->input_address->value('');
        $this->store_selected_country = null;
        $this->store_selected_province = null;
        $this->input_country->value(null);
        $this->input_province
            ->options([])
            ->value(null)
            ->disabled(true)
            ->placeholder('Selecciona primero un pais');
        $this->input_street_number->value('');
        $this->input_postal_code->value('');
    }

    private function fetchCountryOptions(): array
    {
        return Cache::remember('address_form.country_options', now()->addDay(), function (): array {
            try {
                $response = Http::timeout(10)
                    ->acceptJson()
                    ->get(self::COUNTRIES_URL);
            } catch (Throwable) {
                return $this->fallbackCountryOptions();
            }

            if (!$response->successful()) {
                return $this->fallbackCountryOptions();
            }

            $options = collect($response->json('data', []))
                ->map(function (array $country): ?array {
                    $name = trim((string) ($country['name'] ?? ''));

                    if ($name === '') {
                        return null;
                    }

                    return [
                        'value' => $name,
                        'label' => $name,
                    ];
                })
                ->filter()
                ->unique('value')
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return $options !== [] ? $options : $this->fallbackCountryOptions();
        });
    }

    private function fetchProvinceOptions(string $country): array
    {
        return Cache::remember('address_form.province_options.' . md5($country), now()->addDay(), function () use ($country): array {
            try {
                $response = Http::timeout(10)
                    ->acceptJson()
                    ->get(self::STATES_URL, ['country' => $country]);
            } catch (Throwable) {
                return $this->fallbackProvinceOptions($country);
            }

            if (!$response->successful()) {
                return $this->fallbackProvinceOptions($country);
            }

            $options = collect($response->json('data.states', []))
                ->map(function (array $state): ?array {
                    $name = trim((string) ($state['name'] ?? ''));

                    if ($name === '') {
                        return null;
                    }

                    return [
                        'value' => $name,
                        'label' => $name,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            return $options !== [] ? $options : $this->fallbackProvinceOptions($country);
        });
    }

    private function fallbackCountryOptions(): array
    {
        return [
            ['value' => 'Argentina', 'label' => 'Argentina'],
            ['value' => 'Chile', 'label' => 'Chile'],
            ['value' => 'Colombia', 'label' => 'Colombia'],
            ['value' => 'Mexico', 'label' => 'Mexico'],
            ['value' => 'Spain', 'label' => 'Spain'],
        ];
    }

    private function fallbackProvinceOptions(string $country): array
    {
        return match ($country) {
            'Argentina' => [
                ['value' => 'Autonomous City Of Buenos Aires', 'label' => 'Autonomous City Of Buenos Aires'],
                ['value' => 'Buenos Aires Province', 'label' => 'Buenos Aires Province'],
                ['value' => 'Cordoba Province', 'label' => 'Cordoba Province'],
                ['value' => 'Mendoza', 'label' => 'Mendoza'],
            ],
            'Chile' => [
                ['value' => 'Region Metropolitana de Santiago', 'label' => 'Region Metropolitana de Santiago'],
                ['value' => 'Valparaiso', 'label' => 'Valparaiso'],
                ['value' => 'Biobio', 'label' => 'Biobio'],
            ],
            'Colombia' => [
                ['value' => 'Antioquia', 'label' => 'Antioquia'],
                ['value' => 'Bogota D.C.', 'label' => 'Bogota D.C.'],
                ['value' => 'Cundinamarca', 'label' => 'Cundinamarca'],
            ],
            'Mexico' => [
                ['value' => 'Ciudad de Mexico', 'label' => 'Ciudad de Mexico'],
                ['value' => 'Jalisco', 'label' => 'Jalisco'],
                ['value' => 'Nuevo Leon', 'label' => 'Nuevo Leon'],
            ],
            'Spain' => [
                ['value' => 'Andalucia', 'label' => 'Andalucia'],
                ['value' => 'Cataluna', 'label' => 'Cataluna'],
                ['value' => 'Comunidad de Madrid', 'label' => 'Comunidad de Madrid'],
            ],
            default => [],
        };
    }
}
