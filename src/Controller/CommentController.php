<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Member;
use App\Entity\Note;
use App\Repository\CommentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class CommentController extends AbstractController
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var MemberRepository
     */
    private $members;

    public function __construct(Security $security, MemberRepository $members)
    {
        $this->security = $security;
        $this->members = $members;
    }

    /**
     * @Route("/api/comments", name="app_comment")
     * @IsGranted("ROLE_ADMIN")
     */
    public function index(CommentRepository $repository): Response
    {
        return $this->json($repository->findAll(), 200, [], ['groups' => 'comment:read']);
    }

    /**
     * @Route("/api/comments/{id}", name="app_comment_get")
     */
    public function cget(Comment $comment): Response
    {
        return $this->json($comment, 200, [], ['groups' => 'comment:read']);
    }

    /**
     * @Route("/api/comments/{id}", name="app_comment_put")
     * @IsGranted("DELETE", subject="comment")
     */
    public function remove(
        Comment                $comment,
        EntityManagerInterface $em
    ): Response
    {
        try {
            $em->remove($comment);
            $em->flush();

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return $this->json([
                'status' => $e->getCode(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @Route("/api/comments/{id}/comment", name="app_comment_comment", methods={"POST"})
     */
    public function post(
        Request             $request,
        Comment             $comment,
        SerializerInterface $serializer,
        ValidatorInterface  $validator,
        CommentRepository   $repository
    ): Response
    {
        try {
            /** @var Comment $children */
            $children = $serializer->deserialize($request->getContent(), Comment::class, 'json');
            $errors = $validator->validate($comment);

            if ($errors->count() > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $current = $this->getMemberCurrent();
            $children->setAuthor($current);
            $children->setParent($comment);
            $repository->add($children, true);

            return $this->json($comment, Response::HTTP_OK, [], ['groups' => 'comment:read']);
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => Response::HTTP_BAD_REQUEST,
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @Route("/api/comments/{id}", name="app_comment_edit", methods={"PUT"})
     * @IsGranted("EDIT", subject="comment")
     */
    public function edit(
        Request                $request,
        Comment                $comment,
        SerializerInterface    $serializer,
        ValidatorInterface     $validator,
        EntityManagerInterface $em
    ): Response
    {
        try {
            /** @var Comment $comment */
            $comment = $serializer->deserialize($request->getContent(), Comment::class, 'json', ['object_to_populate' => $comment]);
            $errors = $validator->validate($comment);
            if ($errors->count() > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $em->flush();
            return $this->json($comment, Response::HTTP_OK, [], ['groups' => 'comment:read']);
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => Response::HTTP_BAD_REQUEST,
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @Route("/api/comments/{id}/notes", name="app_comment_edit", methods={"POST"})
     */
    public function postNote(
        Request                $request,
        Comment                $comment,
        SerializerInterface    $serializer,
        ValidatorInterface     $validator,
        EntityManagerInterface $em
    ): Response
    {
        try {
            /** @var Note $note */
            $note = $serializer->deserialize($request->getContent(), Note::class, 'json');
            $errors = $validator->validate($note);

            if ($errors->count() > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $current = $this->getMemberCurrent();
            $note->setAuthor($current);
            $note->setComment($comment);
            $em->persist($note);
            $em->flush();

            return $this->json($comment, Response::HTTP_OK, [], ['groups' => 'comment:read']);
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => Response::HTTP_BAD_REQUEST,
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @return Member
     */
    private function getMemberCurrent(): Member
    {
        $current = ($id = $this->security->getUser()->getUserIdentifier()) ? $this->members->find($id) : null;
        if (!$current instanceof Member) {
            throw new InvalidTokenException("The current user not found, please login again.");
        }

        return $current;
    }

}
