<?php

namespace Oro\Bundle\UserBundle\Security;

use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Oro\Bundle\UserBundle\Exception\CredentialsResetException;
use Oro\Bundle\UserBundle\Exception\EmptyOwnerException;
use Oro\Bundle\UserBundle\Exception\OrganizationException;
use Oro\Bundle\UserBundle\Exception\PasswordChangedException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

/**
 * Checks User state during authentication.
 */
class UserChecker extends AbstractUserChecker
{
    protected TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function checkPostAuth(SymfonyUserInterface $user): void
    {
        parent::checkPostAuth($user);

        if ($user instanceof User) {
            if (null !== $user->getAuthStatus() && !$this->hasOrganization($user)) {
                $exception = new OrganizationException();
                $exception->setUser($user);

                throw $exception;
            }
            if (!$user->getOwner()) {
                $exception = new EmptyOwnerException('The user does not have an owner.');
                $exception->setUser($user);

                throw $exception;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkPreAuth(SymfonyUserInterface $user): void
    {
        parent::checkPreAuth($user);

        if (!$user instanceof User) {
            return;
        }

        if (null !== $this->tokenStorage->getToken()
            && null !== $user->getPasswordChangedAt()
            && null !== $user->getLastLogin()
            && $user->getPasswordChangedAt() > $user->getLastLogin()
        ) {
            $exception = new PasswordChangedException('Invalid password.');
            $exception->setUser($user);

            throw $exception;
        }

        if ($user->getAuthStatus() && $user->getAuthStatus()->getId() === UserManager::STATUS_EXPIRED) {
            $exception = new CredentialsResetException('Password reset.');
            $exception->setUser($user);

            throw $exception;
        }
    }

    protected function hasOrganization(User $user): bool
    {
        return $user->getOrganizations(true)->count() > 0;
    }
}
