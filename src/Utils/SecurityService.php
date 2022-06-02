<?php

namespace App\Utils;

use App\Entity\Member;
use App\Repository\MemberRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Symfony\Component\Security\Core\Security;


/**
 * Validator class
 * @package App\Utils
 */
class SecurityService
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var MemberRepository
     */
    private $members;

    /**
     * @param Security $security
     * @param MemberRepository $members
     */
    public function __construct(
        Security         $security,
        MemberRepository $members)
    {

        $this->security = $security;
        $this->members = $members;
    }

    /**
     * @return Member|null
     */
    public function getMember(): ?Member
    {
        $member = $this->security->getUser();
        return $member instanceof Member ? $member : null;
    }

    /**
     * @return Member
     */
    public function getMemberCurrent(): Member
    {
        $id =$this->security->getUser()->getUserIdentifier();
        $current =  $id? $this->members->find($id) : null;
        if ($current instanceof Member) {
            throw new InvalidTokenException("The current user not found, please login again.");
        }

        return $current;
    }


}