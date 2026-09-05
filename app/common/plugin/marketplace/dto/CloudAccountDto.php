<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class CloudAccountDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $nickname,
        public readonly string $avatar = ''
    ) {
    }

    public function toSession(): array
    {
        return ['id' => $this->id, 'username' => $this->username, 'nickname' => $this->nickname, 'avatar' => $this->avatar];
    }
}
