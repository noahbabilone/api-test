<?php

namespace App\Security\Voter;

use App\Entity\Article;
use App\Entity\Member;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ArticleVoter extends AbstractVoter
{

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [parent::EDIT, parent::VIEW, parent::DELETE], true)
            && $subject instanceof Article;
    }

    /**
     * @param string $attribute
     * @param Article $subject
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $current = $token->getUser();
        if (!$current instanceof Member) {
            return false;
        }

        if ($this->isAdmin($token)) {
            return true;
        }

        switch ($attribute) {
            case parent::EDIT:
            case parent::VIEW:
            case parent::DELETE:
                return ($author = $subject->getAuthor()) && $author->getId() === $current->getId();
        }

        return false;
    }
}
