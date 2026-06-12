<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class ApiUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        /** @var list<string> */
        private readonly array $roles,
    ) {
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
