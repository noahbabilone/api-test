<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Member;
use App\Repository\ArticleRepository;
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

/**
 * @Route("/api")
 */
class ArticleController extends AbstractController
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
     * @Route("/articles", name="app_article_index", methods={"GET"})
     */
    public function index(ArticleRepository $repository): Response
    {
        return $this->json($repository->findAll(), 200, [], ['groups' => 'article:read']);
    }

    /**
     * @Route("/articles/{slug}", name="app_article_get", methods={"GET"})
     */
    public function getArticle(Article $article): Response
    {
        return $this->json($article, 200, [], ['groups' => 'article:read']);
    }

    /**
     * @Route("/articles", name="app_article_new", methods={"POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function add(
        Request             $request,
        SerializerInterface $serializer,
        ValidatorInterface  $validator,
        ArticleRepository   $repository
    ): Response
    {
        try {
            /** @var Article $article */
            $article = $serializer->deserialize($request->getContent(), Article::class, 'json');
            $errors = $validator->validate($article);
            if ($errors->count() > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $current = $this->getMemberCurrent();
            $article->setAuthor($current);
            $repository->add($article, true);

            return $this->json($article, Response::HTTP_OK, [], ['groups' => 'article:read']);
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => Response::HTTP_BAD_REQUEST,
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @Route("/articles/{slug}", name="app_article_edit", methods={"PUT"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function edit(
        Request                $request,
        Article                $article,
        SerializerInterface    $serializer,
        ValidatorInterface     $validator,
        EntityManagerInterface $em
    ): Response
    {
        try {
            /** @var Article $article */
            $article = $serializer->deserialize($request->getContent(), Article::class, 'json', ['object_to_populate' => $article]);
            $errors = $validator->validate($article);
            if ($errors->count() > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $em->flush();

            return $this->json($article, Response::HTTP_OK, [], ['groups' => 'article:read']);
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => Response::HTTP_BAD_REQUEST,
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @Route("/articles/{slug}", name="app_article_remove", methods={"DELETE"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function remove(
        Article                $article,
        EntityManagerInterface $em
    ): Response
    {
        try {
            $em->remove($article);
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
     * @Route("/articles/{slug}/comment", name="app_article_comment_post", methods={"POST"})
     */
    public function postComment(
        Request             $request,
        Article             $article,
        SerializerInterface $serializer,
        ValidatorInterface  $validator,
        CommentRepository   $repository
    ): Response
    {
        try {
            /** @var $comment $comment */
            $comment = $serializer->deserialize($request->getContent(), Comment::class, 'json');
            $errors = $validator->validate($comment);

            if ($errors->count() > 0) {
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $current = $this->getMemberCurrent();
            $comment->setAuthor($current);
            $comment->setArticle($article);
            $repository->add($comment, true);

            return $this->json($comment, Response::HTTP_OK, [], ['groups' => 'comment:read']);
        } catch (NotEncodableValueException $exception) {
            return $this->json([
                'status' => Response::HTTP_BAD_REQUEST,
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @Route("/articles/{slug}/comments", name="app_article_comment_get", methods={"GET"})
     */
    public function getComment(
        Request           $request,
        Article           $article,
        CommentRepository $comments
    ): Response
    {
        $data = $comments->getCommentByArticle($article);
        return $this->json($data, 200, [], ['groups' => 'comment:read']);
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
