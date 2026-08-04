<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Enum\IntegrationStatus;
use App\Integration\CalendarWriteInterface;
use App\Integration\Exception\IntegrationException;
use App\Integration\OAuth\OAuthStateSigner;
use App\Integration\OAuthProviderInterface;
use App\Integration\ProviderRegistry;
use App\Message\SyncConnectedAccount;
use App\Repository\ConnectedAccountRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integration', name: 'api_integration_')]
class IntegrationController extends AbstractController
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ConnectedAccountRepository $connectedAccountRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly OAuthStateSigner $stateSigner,
        private readonly RateLimiterFactoryInterface $syncLimiter,
        private readonly RateLimiterFactoryInterface $testLimiter,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string $frontendUrl,
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
            $is_oauth = $provider instanceof OAuthProviderInterface;
            $configured = !$is_oauth || $provider->is_configured();

            $providers[] = [
                'key' => $provider->get_key(),
                'label' => $provider->get_label(),
                // A form provider is connected by typing credentials; an oauth one
                // by being redirected. The frontend branches on this rather than on
                // the provider key.
                'auth' => $is_oauth ? 'oauth' : 'form',
                'form' => $provider->get_credential_form()->get_fields(),
                'available' => $configured,
                'unavailable_reason' => $configured ? null : 'This provider is not configured on this server.',
                'account' => $account ? $this->serialize_account($account) : null,
            ];
        }

        return new JsonResponse($providers, Response::HTTP_OK);
    }

    /**
     * Returns the consent URL as JSON rather than issuing a 302: the frontend calls
     * this with a Bearer token from fetch(), which would transparently follow a
     * redirect and hand Google an XHR it cannot answer. The browser navigation has
     * to be the frontend's own window.location.assign().
     */
    #[Route('/oauth/{provider}/start', name: 'oauth_start', methods: ['GET'])]
    public function oauth_start(string $provider): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->registry->has($provider)) {
            return new JsonResponse(['error' => 'Unknown provider.'], Response::HTTP_BAD_REQUEST);
        }

        $candidate = $this->registry->get($provider);

        if (!$candidate instanceof OAuthProviderInterface) {
            return new JsonResponse(['error' => 'This provider does not use OAuth.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$candidate->is_configured()) {
            return new JsonResponse(['error' => 'This provider is not configured on this server.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $this->connectedAccountRepository->findOneByUserAndProvider($user, $provider);

        // An account stuck in ERROR is exactly the one the user needs to reconnect,
        // so only a healthy account blocks a fresh consent.
        if ($existing instanceof ConnectedAccount && IntegrationStatus::ERROR !== $existing->getStatus()) {
            return new JsonResponse(['error' => 'This provider is already connected.'], Response::HTTP_CONFLICT);
        }

        $limit = $this->testLimiter->create('oauth-'.$user->getId())->consume();

        if (!$limit->isAccepted()) {
            return $this->too_many_requests($limit);
        }

        $state = $this->stateSigner->sign((int) $user->getId(), $provider);

        return new JsonResponse(
            ['authorization_url' => $candidate->get_authorization_url($state)],
            Response::HTTP_OK,
        );
    }

    /**
     * Public: the provider redirects the browser here with no Authorization header
     * and no session, so the user is derived from the signed state and never from
     * getUser(), which would be null.
     *
     * Always redirects back to the frontend. A 500 rendered into the user's browser
     * mid-OAuth is both a dead end and an information leak.
     */
    #[Route('/oauth/callback', name: 'oauth_callback', methods: ['GET'])]
    public function oauth_callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        $denied = (string) $request->query->get('error', '');

        try {
            $claims = $this->stateSigner->verify($state);
        } catch (IntegrationException $exception) {
            // The state is what tells us which provider this was, so without a
            // valid one there is nothing to report against.
            return $this->oauth_redirect(null, false, $exception->getMessage());
        }

        $provider_key = $claims['provider'];

        if ('' !== $denied) {
            return $this->oauth_redirect($provider_key, false, 'Access was not granted.');
        }

        if ('' === $code) {
            return $this->oauth_redirect($provider_key, false, 'Google did not return an authorization code.');
        }

        $user = $this->userRepository->find($claims['user_id']);

        if (!$user instanceof User || !$this->registry->has($provider_key)) {
            return $this->oauth_redirect($provider_key, false, 'That sign-in is no longer valid.');
        }

        $provider = $this->registry->get($provider_key);

        if (!$provider instanceof OAuthProviderInterface) {
            return $this->oauth_redirect($provider_key, false, 'That provider does not use OAuth.');
        }

        $existing = $this->connectedAccountRepository->findOneByUserAndProvider($user, $provider_key);

        try {
            $account = $provider->complete_authorization($user, $code, $existing);
        } catch (IntegrationException $exception) {
            return $this->oauth_redirect($provider_key, false, $exception->getMessage());
        }

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new SyncConnectedAccount((int) $account->getId()));

        return $this->oauth_redirect($provider_key, true, null);
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

        // An OAuth provider has no credentials to post. Refuse before anything
        // reads the bag, so a client cannot smuggle a hand-made token in here.
        if ($this->registry->get($key) instanceof OAuthProviderInterface) {
            return new JsonResponse(
                ['error' => 'This provider is connected through its own sign-in flow.'],
                Response::HTTP_BAD_REQUEST,
            );
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

        $limit = $this->testLimiter->create($this->limiter_key($account))->consume();

        if (!$limit->isAccepted()) {
            return $this->too_many_requests($limit);
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

        // Every sync makes real requests to iCloud, so hammering the button must
        // not queue a job per click.
        $limit = $this->syncLimiter->create($this->limiter_key($account))->consume();

        if (!$limit->isAccepted()) {
            return $this->too_many_requests($limit);
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
     * Back to Settings with the outcome in the query string, which the frontend
     * turns into its usual banner and then strips.
     *
     * Every value is urlencoded. $message only ever originates from our own
     * IntegrationExceptions, never from the provider's query string — echoing
     * Google's `error` param back verbatim would be a reflected-content hole.
     */
    private function oauth_redirect(?string $provider, bool $ok, ?string $message): RedirectResponse
    {
        $params = [
            'integration' => $provider ?? 'unknown',
            'status' => $ok ? 'connected' : 'error',
        ];

        if (null !== $message && '' !== $message) {
            $params['message'] = $message;
        }

        return new RedirectResponse(
            rtrim($this->frontendUrl, '/').'/settings?'.http_build_query($params),
        );
    }

    /**
     * Throttle per account, not per IP: two users behind one NAT must not
     * exhaust each other's budget.
     */
    private function limiter_key(ConnectedAccount $account): string
    {
        return 'integration-'.$account->getId();
    }

    private function too_many_requests(RateLimit $limit): JsonResponse
    {
        $retry_after = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        return new JsonResponse(
            [
                'error' => 'Too many sync requests. Try again shortly.',
                'retry_after' => $retry_after,
            ],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $retry_after],
        );
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
     * Permissions the provider asks for that this account has not granted.
     *
     * Non-empty means the grant predates a scope the provider has since added —
     * a Gmail-only connection made before Calendar existed, say. The sync skips
     * that half rather than failing, so the UI needs this to explain the gap and
     * offer a reconnect.
     *
     * @return list<string>
     */
    private function missing_scopes(ConnectedAccount $account): array
    {
        $provider = $this->registry->get((string) $account->getProvider());

        if (!$provider instanceof OAuthProviderInterface) {
            return [];
        }

        return array_values(array_diff($provider->get_scopes(), $account->getScopes() ?? []));
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
            'missing_scopes' => $this->missing_scopes($account),
        ];
    }
}
