<?php

namespace App\Security\Voter;

use App\Entity\Member;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

abstract class AbstractVoter extends Voter
{
    public const EDIT = 'EDIT';
    public const VIEW = 'VIEW';
    protected const DELETE = 'DELETE';

    /**
     * @var AccessDecisionManagerInterface
     */
    private $decisionManager;

    public function __construct(AccessDecisionManagerInterface $decisionManager)
    {
        $this->decisionManager = $decisionManager;
    }

    /**
     * @param TokenInterface $token
     * @return bool
     */
    public function isAdmin(TokenInterface $token): bool
    {
        return $this->decisionManager->decide($token, [Member::ADMIN]);
    }

    /**
     * @param TokenInterface $token
     * @return bool
     */
    public function checkCurrentMember(TokenInterface $token): bool
    {
        return $token->getUser() instanceof Member;
    }
}
