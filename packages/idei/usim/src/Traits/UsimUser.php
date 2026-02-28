<?php

namespace Idei\Usim\Traits;

use Idei\Usim\Notifications\ResetPasswordNotification;
use Idei\Usim\Notifications\CustomVerifyEmailNotification;

/**
 * Trait UsimUser
 *
 * Adds USIM-specific behavior to the User model.
 * Includes custom notification overrides for password reset and email verification.
 *
 * Usage: Add `use UsimUser;` to your User model alongside HasApiTokens and HasRoles.
 */
trait UsimUser
{
    /**
     * Send the password reset notification using USIM's custom notification.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification using USIM's custom notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomVerifyEmailNotification());
    }
}
