<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\RegisterService;
use Idei\Usim\Screen;

class RegisterActionHandler
{
    public function __construct(
        protected RegisterService $registerService,
        protected AuthSessionService $authSessionService
    ) {
    }

    /**
     * @param Screen $caller
     * @param array<string, mixed> $params
     */
    public function handle(Screen $caller, array $params): void
    {
        $acceptTerms = $params['accept_terms'] ?? false;
        if ($acceptTerms === false || $acceptTerms === 'false' || $acceptTerms === 0) {
            $caller->toast(t('screen.menu.register_terms_required'), type: 'error');
            return;
        }

        $name = is_string($params['name'] ?? null) ? $params['name'] : '';
        $email = is_string($params['email'] ?? null) ? $params['email'] : '';
        $password = is_string($params['password'] ?? null) ? $params['password'] : '';
        $passwordConfirmation = is_string($params['password_confirmation'] ?? null) ? $params['password_confirmation'] : '';
        $roles = $this->normalizeRoles($params['roles'] ?? [config('usim.default_registering_role')]);
        $sendEmail = !isset($params['send_verification_email']) || (bool) $params['send_verification_email'];

        $response = $this->registerService->register(
            name: $name,
            email: $email,
            password: $password,
            passwordConfirmation: $passwordConfirmation,
            roles: $roles,
            sendVerificationEmail: $sendEmail
        );

        if ($response['status'] !== 'success') {
            $this->handleError($caller, $response);
            return;
        }

        $this->handleSuccess($caller, $response);
    }

    /**
     * @param Screen $caller
     * @param array<string, mixed> $response
     */
    protected function handleSuccess(Screen $caller, array $response): void
    {
        $messageValue = $response['message'] ?? t('screen.menu.register_success_default');
        $message = \is_string($messageValue) ? $messageValue : t('screen.menu.register_success_default');
        $caller->toast(
            message: $message,
            type: 'success'
        );

        $user = $response['user'] ?? null;
        if (!$user instanceof User) {
            $caller->closeModal();
            return;
        }

        $token = data_get($response, 'data.token');
        $redirectTo = $this->authSessionService->start($user, null, is_string($token) ? $token : null);
        $caller->redirect($redirectTo);
    }

    /**
     * @param Screen $caller
     * @param array<string, mixed> $response
     */
    protected function handleError(Screen $caller, array $response): void
    {
        $messageValue = $response['message'] ?? t('screen.menu.validation_errors_default');
        $message = is_string($messageValue) ? $messageValue : t('screen.menu.validation_errors_default');
        $caller->toast(
            message: $message,
            type: 'error',
            position: 'top-middle'
        );

        $errors = $this->normalizeErrors($response['errors'] ?? []);
        if (!empty($errors)) {
            $modalUpdates = [];
            foreach ($errors as $fieldName => $messages) {
                $modalUpdates[$fieldName] = [
                    'error' => implode(' ', $this->normalizeStringList($messages)),
                ];
            }
            $caller->updateModal($modalUpdates);
        }
    }

    /**
     * @param mixed $roles
     * @return list<string>
     */
    protected function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles)) {
            return [$roles];
        }

        if (!is_array($roles)) {
            return ['user'];
        }

        return $this->normalizeStringList($roles) ?: ['user'];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function normalizeErrors(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $messages) {
            if (is_string($key)) {
                $normalized[$key] = $messages;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    protected function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }
}
