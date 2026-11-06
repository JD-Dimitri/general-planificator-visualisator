<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Socle commun des contrôleurs d'API : compte courant, lecture du corps JSON,
 * validation et format de réponse.
 *
 * Une requête PATCH ne porte que les champs modifiés. Le DTO est donc d'abord
 * rempli depuis l'entité, puis le JSON reçu écrit par-dessus : un champ absent
 * garde sa valeur, et un champ explicitement à null est bien effacé.
 */
abstract class AbstractApiController extends AbstractController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly SerializerInterface $serializer,
        protected readonly ValidatorInterface $validator,
    ) {
    }

    protected function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    /** @param list<string> $groups */
    protected function ok(mixed $data, int $status = Response::HTTP_OK, array $groups = ['read']): JsonResponse
    {
        return new JsonResponse(
            $this->serializer->serialize($data, 'json', [
                'groups' => $groups,
                'datetime_format' => \DateTimeInterface::ATOM,
            ]),
            $status,
            [],
            true
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $dtoClass
     * @param T|null          $populate DTO pré-rempli depuis l'entité, pour un PATCH
     *
     * @return T
     */
    protected function input(Request $request, string $dtoClass, ?object $populate = null): object
    {
        $body = trim($request->getContent());

        if ('' === $body) {
            $body = '{}';
        }

        try {
            $context = [];
            if (null !== $populate) {
                $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $populate;
            }

            /** @var T $dto */
            $dto = $this->serializer->deserialize($body, $dtoClass, 'json', $context);
        } catch (\Throwable) {
            throw new BadRequestHttpException('Corps de requête JSON invalide.');
        }

        return $dto;
    }

    protected function violations(object $dto): ?JsonResponse
    {
        $errors = $this->validator->validate($dto);

        return \count($errors) > 0 ? $this->violationResponse($errors) : null;
    }

    protected function violationResponse(ConstraintViolationListInterface $errors): JsonResponse
    {
        $fields = [];
        foreach ($errors as $error) {
            $fields[$error->getPropertyPath() ?: '_'][] = (string) $error->getMessage();
        }

        return new JsonResponse([
            'error' => 'validation_failed',
            'message' => (string) $errors->get(0)->getMessage(),
            'fields' => $fields,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
