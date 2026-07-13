<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Integration\CalendarWriteInterface;
use App\Integration\Exception\IntegrationException;
use App\Integration\ProviderRegistry;
use App\Message\SyncConnectedAccount;
use App\Repository\ConnectedAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integration', name: 'api_integration_')]
class IntegrationController extends AbstractController
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ConnectedAccountRepository $connectedAccountRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/providers', name: 'providers', methods: ['GET'])]
    public function providers(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $providers = [];

        foreach ($this->registry->all() as $provider) {
            $account = $this->connectedAccountRepository->findOneByUserAndProvider($user, $provider->get_key());

            $providers[] = [
                'key' => $provider->get_key(),
                'label' => $provider->get_label(),
                'form' => $provider->get_credential_form()->get_fields(),
                'account' => $account ? $this->serialize_account($account) : null,
            ];
        }

        return new JsonResponse($providers, Response::HTTP_OK);
    }

    #[Route('/accounts', name: 'accounts', methods: ['GET'])]
    public function accounts(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $accounts = $this->connectedAccountRepository->findByUser($user);

        return new JsonResponse(array_map([$this, 'serialize_account'], $accounts), Response::HTTP_OK);
    }

    #[Route('/accounts', name: 'connect', methods: ['POST'])]
    public function connect(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload) || !isset($payload['provider'])) {
            return new JsonResponse(['error' => 'A provider is required.'], Response::HTTP_BAD_REQUEST);
        }

        $key = (string) $payload['provider'];

        if (!$this->registry->has($key)) {
            return new JsonResponse(['error' => 'Unknown provider.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->connectedAccountRepository->findOneByUserAndProvider($user, $key)) {
            return new JsonResponse(['error' => 'This provider is already connected.'], Response::HTTP_CONFLICT);
        }

        $credentials = \is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];

        try {
            $account = $this->registry->get($key)->connect($user, $credentials);
        } catch (IntegrationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new SyncConnectedAccount((int) $account->getId()));

        return new JsonResponse($this->serialize_account($account), Response::HTTP_CREATED);
    }

    #[Route('/accounts/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $account = $this->find_owned_account($id);

        if (!$account instanceof ConnectedAccount) {
            return $this->deny();
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload) || !\array_key_exists('target_calendar_href', $payload)) {
            return new JsonResponse(['error' => 'A target_calendar_href is required.'], Response::HTTP_BAD_REQUEST);
        }

        $target = $payload['target_calendar_href'];

        if (null === $target) {
            $account->setTargetCalendarHref(null);
            $this->entityManager->flush();

            return new JsonResponse($this->serialize_account($account), Response::HTTP_OK);
        }

        $known = array_column($this->list_calendars($account), 'href');

        // Only ever write to a calendar we actually discovered, never to an
        // arbitrary URL supplied by the client.
        if (!\in_array($target, $known, true)) {
            return new JsonResponse(['error' => 'That calendar does not belong to this account.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $account->setTargetCalendarHref((string) $target);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize_account($account), Response::HTTP_OK);
    }

    #[Route('/accounts/{id}/test', name: 'test', methods: ['POST'])]
    public function test(int $id): JsonResponse
    {
        $account = $this->find_owned_account($id);

        if (!$account instanceof ConnectedAccount) {
            return $this->deny();
        }

        try {
            $this->registry->get((string) $account->getProvider())->test_connection($account);
        } catch (IntegrationException $exception) {
            return new JsonResponse(['ok' => false, 'error' => $exception->getMessage()], Response::HTTP_OK);
        }

        return new JsonResponse(['ok' => true, 'error' => null], Response::HTTP_OK);
    }

    #[Route('/accounts/{id}/sync', name: 'sync', methods: ['POST'])]
    public function sync(int $id): JsonResponse
    {
        $account = $this->find_owned_account($id);

        if (!$account instanceof ConnectedAccount) {
            return $this->deny();
        }

        $this->messageBus->dispatch(new SyncConnectedAccount((int) $account->getId()));

        return new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    #[Route('/accounts/{id}', name: 'disconnect', methods: ['DELETE'])]
    public function disconnect(int $id): JsonResponse
    {
        $account = $this->find_owned_account($id);

        if (!$account instanceof ConnectedAccount) {
            return $this->deny();
        }

        $this->registry->get((string) $account->getProvider())->disconnect($account);

        // Imported events cascade away with the account, which is the
        // privacy-correct default here.
        $this->entityManager->remove($account);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function find_owned_account(int $id): ?ConnectedAccount
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return null;
        }

        $account = $this->connectedAccountRepository->find($id);

        if (!$account || $account->getUserOwner() !== $user) {
            return null;
        }

        return $account;
    }

    private function deny(): JsonResponse
    {
        return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
    }

    /**
     * The calendars this account can write to. Read from the sync state, so no
     * network call. Empty for providers that cannot write calendars.
     *
     * @return list<array{href: string, display_name: string}>
     */
    private function list_calendars(ConnectedAccount $account): array
    {
        $provider = $this->registry->get((string) $account->getProvider());

        if (!$provider instanceof CalendarWriteInterface) {
            return [];
        }

        return $provider->list_target_calendars($account);
    }

    /**
     * Never exposes the credential bag.
     *
     * @return array<string, mixed>
     */
    private function serialize_account(ConnectedAccount $account): array
    {
        return [
            'id' => $account->getId(),
            'provider' => $account->getProvider(),
            'display_name' => $account->getDisplayName(),
            'status' => $account->getStatus()->value,
            'last_error' => $account->getLastError(),
            'last_synced_at' => $account->getLastSyncedAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $account->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'target_calendar_href' => $account->getTargetCalendarHref(),
            'calendars' => $this->list_calendars($account),
        ];
    }
}
