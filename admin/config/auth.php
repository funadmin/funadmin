<?php

declare(strict_types=1);

return ['token_ttl' => (int) (getenv('AUTH_TOKEN_TTL') ?: 7200)];
