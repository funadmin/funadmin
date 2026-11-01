<?php

declare(strict_types=1);

return ['issuer' => (string) (getenv('IDENTITY_ISSUER') ?: '')];
