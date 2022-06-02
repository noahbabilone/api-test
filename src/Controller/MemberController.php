<?php

namespace App\Controller;

use App\Repository\MemberRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/members")
 */
class MemberController extends AbstractController
{
    /**
     * @Route("", name="app_member_index")
     */
    public function index(MemberRepository $repository): Response
    {
        return $this->json($repository->findAll(), 200, [], ['groups' => 'member:read']);
    }
}
