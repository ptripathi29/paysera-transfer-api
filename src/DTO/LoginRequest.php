<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly ?string $username = null,

        #[Assert\NotBlank]
        public readonly ?string $password = null,
    ) {
    }
}
