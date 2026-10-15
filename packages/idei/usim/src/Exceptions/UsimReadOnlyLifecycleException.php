<?php

namespace Idei\Usim\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a Screen attempts to mutate UI components
 * during a read-only GET query lifecycle.
 */
class UsimReadOnlyLifecycleException extends RuntimeException {}
