<?php

declare(strict_types=1);

return [
    'issuer' => (string) (getenv('IDENTITY_ISSUER') ?: ''),
    'subject_pepper' => (string) (getenv('OAUTH_SUBJECT_PEPPER') ?: ''),
];
