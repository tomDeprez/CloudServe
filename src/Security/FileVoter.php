<?php

namespace App\Security;

use App\Entity\File;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FileVoter extends Voter
{
    const VIEW = 'FILE_VIEW';
    const DELETE = 'FILE_DELETE';
    const DOWNLOAD = 'FILE_DOWNLOAD';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::DELETE, self::DOWNLOAD])
            && $subject instanceof File;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var File $file */
        $file = $subject;

        // Les admins ont tous les droits
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Les utilisateurs ne peuvent accéder qu'à leurs propres fichiers
        return $file->getUser() === $user;
    }
}
