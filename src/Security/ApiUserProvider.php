<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<ApiUser>
 */
final class ApiUserProvider implements UserProviderInterface
{
    /** @var array<string, array{password: string, roles: list<string>}> */
    private array $users = [
        'paysera_api' => [
            'password' => '$2y$13$PayseraDemoHashReplaceInProductionxxxxxxxxxxxxxxxxxxx',
            'roles' => ['ROLE_API_USER'],
        ],
        'paysera_readonly' => [
            'password' => '$2y$13$PayseraDemoHashReplaceInProductionxxxxxxxxxxxxxxxxxxx',
            'roles' => ['ROLE_API_READONLY'],
        ],
    ];

    public function __construct()
    {
        // Demo credentials for assignment: paysera_api / PayseraDemo123!
        $this->users['paysera_api']['password'] = password_hash('PayseraDemo123!', PASSWORD_BCRYPT);
        $this->users['paysera_readonly']['password'] = password_hash('ReadOnlyDemo123!', PASSWORD_BCRYPT);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (!isset($this->users[$identifier])) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return new ApiUser($identifier, $this->users[$identifier]['password'], $this->users[$identifier]['roles']);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === ApiUser::class;
    }

    public function validateCredentials(string $username, string $plainPassword): bool
    {
        if (!isset($this->users[$username])) {
            return false;
        }

        return password_verify($plainPassword, $this->users[$username]['password']);
    }
}
