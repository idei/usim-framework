<?php

namespace App\UI\Screens\Auth;

use Idei\Usim\Events\UsimEvent;
use Idei\Usim\UIBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Idei\Usim\Screen;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\Components\UIContainer;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\UploaderBuilder;

class Profile extends Screen
{
    protected Input $input_email;
    protected Input $input_name;
    protected UploaderBuilder $uploader_profile;

    public static function authorize(): bool
    {
        // This screen should only be accessible to authenticated users
        return self::requireAuth();
    }

    public static function getMenuLabel(): string
    {
        return 'Profile';
    }

    public static function getMenuIcon(): ?string
    {
        return '👤';
    }

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $user = Auth::user();

        $container
            ->title(t('screen.auth.profile.title'))
            ->maxWidth('600px')
            ->centerHorizontal()
            ->shadow(2)
            ->padding('30px');

        // Título
        $container->add(
            UIBuilder::label('lbl_title')
                ->text("👤 Configuración de Perfil")
                ->style('primary')
                ->fontSize(20)
                ->fontWeight('bold')
        );

        // Email (readonly)
        $this->input_email = UIBuilder::input('input_email')
            ->label(t('screen.auth.profile.email.label'))
            ->type('email')
            ->value($user->email)
            ->disabled(true)
            ->width('100%');

        $container->add($this->input_email);

        // Nombre
        $this->input_name = UIBuilder::input('input_name')
            ->label(t('screen.auth.profile.name.label'))
            ->type('text')
            ->placeholder(t('screen.auth.profile.name.placeholder'))
            ->value($user->name ?? '')
            ->required(true)
            ->width('100%');

        $container->add($this->input_name);

        // Foto de perfil
        $this->uploader_profile = UIBuilder::uploader('uploader_profile')
            ->allowedTypes(['image/*'])
            ->label(t('screen.auth.profile.photo.label'))
            ->maxFiles(1)
            ->maxSize(2)
            ->aspect('1:1')
            ->size(1);

        $container->add($this->uploader_profile);

        // Botones de acción
        $container->add(
            UIBuilder::button('btn_save_profile')
                ->label(t('screen.auth.profile.actions.save'))
                ->action('save_profile')
                ->style('primary')
                ->width('100%')
        );

        $container->add(
            UIBuilder::button('btn_change_password')
                ->label(t('screen.auth.profile.actions.change_password'))
                ->action('change_password')
                ->style('secondary')
                ->width('100%')
        );
    }

    protected function postLoadUI(): void
    {
        $user = Auth::user();

        // Actualizar inputs con datos actuales del usuario
        $this->input_email->value($user->email ?? '');
        $this->input_name->value($user->name ?? '');

        if (!$user->email_verified_at) {
            $this->input_email->error(t('screen.auth.profile.email.not_verified'));
        } else {
            $this->input_email->error(null);
        }

        $imageUrl = null;

        // Actualizar uploader con imagen actual (si existe)
        if ($user->profile_image) {
            $imageUrl = UploadService::fileUrl("uploads/images/{$user->profile_image}") . '?t=' . time();
        }

        $this->uploader_profile->existingFile($imageUrl);
    }

    /**
     * Guardar cambios del perfil
     */
    public function onSaveProfile(array $params): void
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Obtener datos del formulario
            $name = trim($params['input_name'] ?? '');

            if (empty($name)) {
                $this->input_name->error(t('screen.auth.profile.validation.name_required'));
                return;
            }

            // Actualizar nombre
            $user->name = $name;

            // Procesar imagen de perfil si fue subida
            if ($filename = $this->uploader_profile->confirm($params, 'images', $user->profile_image)) {
                $user->profile_image = $filename;
            }

            // Guardar cambios
            $user->save();
            $this->input_name->error(null);

            event(new UsimEvent('updated_profile', [
                'user' => $user
            ]));

            // Mostrar éxito
            $this->toast(t('screen.auth.profile.toast.updated'), 'success');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error saving profile: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->toast(t('screen.auth.profile.toast.save_error', ['message' => $e->getMessage()]), 'error');
        }
    }

    /**
     * Reenviar email de verificación
     */
    public function onResendVerification(array $params): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->email_verified_at) {
            $this->toast(t('screen.auth.profile.toast.already_verified'), 'info');
            return;
        }

        // Enviar notificación de verificación
        $user->sendEmailVerificationNotification();

        $this->toast(t('screen.auth.profile.toast.verification_sent'), 'success');
    }

    /**
     * Cambiar contraseña
     */
    public function onChangePassword(array $params): void
    {
        $user = Auth::user();

        // Enviar email de reset de contraseña
        $status = Password::sendResetLink([
            'email' => $user->email
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->toast(t('screen.auth.profile.toast.password_link_sent'), 'success');
        } else {
            $this->toast(t('screen.auth.profile.toast.password_link_error'), 'error');
        }
    }
}
