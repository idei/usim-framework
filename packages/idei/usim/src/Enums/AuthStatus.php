<?php

namespace Idei\Usim\Enums;

enum AuthStatus: string
{
    case SUCCESS = 'success';
    case BAD_CREDENTIALS = 'bad_credentials';
    case VALIDATION_ERROR = 'validation_error';
    case TERMS_REQUIRED = 'terms_required';
    case DEVICE_UNPAIRED = 'device_unpaired';
    case USER_DISABLED = 'user_disabled';
    case ERROR = 'error';
}

