<?php

declare(strict_types=1);

namespace App\Integration\Apple;

use App\Integration\Exception\CalDavConflictException;
use App\Integration\Exception\IntegrationException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Minimal CalDAV client. iCloud exposes no REST API and no OAuth, so this
 * speaks the raw protocol: PROPFIND/REPORT/PUT/DELETE with 207 Multi-Status
 * XML responses.
 */
final class CalDavClient
{
    public const BASE_URL = 'https://caldav.icloud.com';

    private const NS_DAV = 'DAV:';
    private const NS_CALDAV = 'urn:ietf:params:xml:ns:caldav';

    public function __construct(private readonly HttpClientInterface $http_client)
    {
    }

    public function discover_principal(string $username, string $password): string
    {
        $body = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <d:propfind xmlns:d="DAV:">
              <d:prop><d:current-user-principal/></d:prop>
            </d:propfind>
            XML;

        $xpath = $this->request('PROPFIND', self::BASE_URL.'/', $username, $password, $body, '0');
        $href = $this->query_text($xpath, '//d:current-user-principal/d:href');

        if (!$href) {
            throw new IntegrationException('CalDAV server did not return a user principal.');
        }

        return $this->resolve_url(self::BASE_URL, $href);
    }

    public function discover_calendar_home(string $principal_url, string $username, string $password): string
    {
        $body = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
              <d:prop><cal:calendar-home-set/></d:prop>
            </d:propfind>
            XML;

        $xpath = $this->request('PROPFIND', $principal_url, $username, $password, $body, '0');
        $href = $this->query_text($xpath, '//cal:calendar-home-set/d:href');

        if (!$href) {
            throw new IntegrationException('CalDAV server did not return a calendar home.');
        }

        return $this->resolve_url($principal_url, $href);
    }

    /**
     * Only collections that actually hold VEVENTs. iCloud also exposes reminder
     * (VTODO) collections under the same home, and they must not be synced here.
     *
     * @return list<array{href: string, display_name: string}>
     */
    public function list_calendars(string $home_url, string $username, string $password): array
    {
        $body = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
              <d:prop>
                <d:resourcetype/>
                <d:displayname/>
                <cal:supported-calendar-component-set/>
              </d:prop>
            </d:propfind>
            XML;

        $xpath = $this->request('PROPFIND', $home_url, $username, $password, $body, '1');
        $calendars = [];

        foreach ($this->query_elements($xpath, '//d:response') as $response) {
            $href = $this->query_text($xpath, './/d:href', $response);

            if (!$href || !$this->query_elements($xpath, './/d:resourcetype/cal:calendar', $response)) {
                continue;
            }

            $supports_events = false;

            foreach ($this->query_elements($xpath, './/cal:supported-calendar-component-set/cal:comp', $response) as $comp) {
                if ('VEVENT' === $comp->getAttribute('name')) {
                    $supports_events = true;
                }
            }

            if (!$supports_events) {
                continue;
            }

            $calendars[] = [
                'href' => $this->resolve_url($home_url, $href),
                'display_name' => $this->query_text($xpath, './/d:displayname', $response) ?? 'Calendar',
            ];
        }

        return $calendars;
    }

    /**
     * RFC 6578 incremental sync. A null token means "enumerate everything".
     * Returns hrefs only; bodies come from multiget().
     *
     * @return array{sync_token: ?string, changed: list<string>, deleted: list<string>}
     */
    public function sync_collection(string $calendar_url, ?string $sync_token, string $username, string $password): array
    {
        $token_element = $sync_token ? '<d:sync-token>'.htmlspecialchars($sync_token, \ENT_XML1).'</d:sync-token>' : '<d:sync-token/>';

        $body = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <d:sync-collection xmlns:d="DAV:">
              {$token_element}
              <d:sync-level>1</d:sync-level>
              <d:prop><d:getetag/></d:prop>
            </d:sync-collection>
            XML;

        $xpath = $this->request('REPORT', $calendar_url, $username, $password, $body, '1');

        $changed = [];
        $deleted = [];

        foreach ($this->query_elements($xpath, '//d:response') as $response) {
            $href = $this->query_text($xpath, './/d:href', $response);

            if (!$href) {
                continue;
            }

            $status = $this->query_text($xpath, './/d:status', $response) ?? '';
            $href = $this->resolve_url($calendar_url, $href);

            if (str_contains($status, '404')) {
                $deleted[] = $href;

                continue;
            }

            if (str_ends_with($href, '.ics')) {
                $changed[] = $href;
            }
        }

        return [
            'sync_token' => $this->query_text($xpath, '/d:multistatus/d:sync-token'),
            'changed' => $changed,
            'deleted' => $deleted,
        ];
    }

    /**
     * Bounded full enumeration. Used for the first sync and whenever a sync
     * token goes stale.
     *
     * @return array<string, array{etag: ?string, ics: string}> href => resource
     */
    public function calendar_query(string $calendar_url, \DateTimeImmutable $from, \DateTimeImmutable $to, string $username, string $password): array
    {
        $start = $from->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $end = $to->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');

        $body = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <cal:calendar-query xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
              <d:prop>
                <d:getetag/>
                <cal:calendar-data/>
              </d:prop>
              <cal:filter>
                <cal:comp-filter name="VCALENDAR">
                  <cal:comp-filter name="VEVENT">
                    <cal:time-range start="{$start}" end="{$end}"/>
                  </cal:comp-filter>
                </cal:comp-filter>
              </cal:filter>
            </cal:calendar-query>
            XML;

        $xpath = $this->request('REPORT', $calendar_url, $username, $password, $body, '1');

        return $this->extract_calendar_data($xpath, $calendar_url);
    }

    /**
     * @param list<string> $hrefs
     *
     * @return array<string, array{etag: ?string, ics: string}> href => resource
     */
    public function multiget(string $calendar_url, array $hrefs, string $username, string $password): array
    {
        if (!$hrefs) {
            return [];
        }

        $href_elements = '';

        foreach ($hrefs as $href) {
            $href_elements .= '<d:href>'.htmlspecialchars($this->to_path($href), \ENT_XML1).'</d:href>';
        }

        $body = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <cal:calendar-multiget xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
              <d:prop>
                <d:getetag/>
                <cal:calendar-data/>
              </d:prop>
              {$href_elements}
            </cal:calendar-multiget>
            XML;

        $xpath = $this->request('REPORT', $calendar_url, $username, $password, $body, '1');

        return $this->extract_calendar_data($xpath, $calendar_url);
    }

    /**
     * Creates or replaces a calendar resource. Returns the new ETag when the
     * server sends one; iCloud sometimes omits it, in which case the next pull
     * refreshes it.
     *
     * @throws CalDavConflictException when the remote changed under us (412)
     */
    public function put_event(string $href, string $ics, ?string $etag, string $username, string $password): ?string
    {
        $headers = ['Content-Type' => 'text/calendar; charset=utf-8'];

        if (null !== $etag) {
            // Conditional replace: fails with 412 if someone edited it since.
            $headers['If-Match'] = $etag;
        } else {
            // Create: fails if a resource already lives at this href.
            $headers['If-None-Match'] = '*';
        }

        $result = $this->write_request('PUT', $href, $username, $password, $ics, $headers);

        if (412 === $result['status'] || 409 === $result['status']) {
            throw new CalDavConflictException('This event changed on iCloud since Pryvora last read it.');
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new IntegrationException(\sprintf('iCloud rejected the event with status %d.', $result['status']));
        }

        return $result['etag'];
    }

    /**
     * 404/410 count as success: the resource is already gone, which is the
     * outcome we wanted.
     *
     * @throws CalDavConflictException when the remote changed under us (412)
     */
    public function delete_event(string $href, ?string $etag, string $username, string $password): void
    {
        $headers = [];

        if (null !== $etag) {
            $headers['If-Match'] = $etag;
        }

        $result = $this->write_request('DELETE', $href, $username, $password, null, $headers);

        if (412 === $result['status']) {
            throw new CalDavConflictException('This event changed on iCloud since Pryvora last read it.');
        }

        if (404 === $result['status'] || 410 === $result['status']) {
            return;
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new IntegrationException(\sprintf('iCloud refused to delete the event, status %d.', $result['status']));
        }
    }

    /**
     * PUT and DELETE return no body worth parsing, only a status and (on PUT) an
     * ETag, so they cannot go through request(), which is XML-in/XPath-out.
     * getHeaders(false) keeps a 4xx from throwing so the caller can act on a 412
     * rather than unwrap a ClientException.
     *
     * @param array<string, string> $headers
     *
     * @return array{status: int, etag: ?string}
     */
    private function write_request(string $method, string $url, string $username, string $password, ?string $body, array $headers): array
    {
        $options = [
            'auth_basic' => [$username, $password],
            'headers' => $headers,
        ];

        if (null !== $body) {
            $options['body'] = $body;
        }

        try {
            $response = $this->http_client->request($method, $url, $options);

            $status = $response->getStatusCode();
            $response_headers = $response->getHeaders(false);
        } catch (ExceptionInterface $exception) {
            throw new IntegrationException('Could not reach the CalDAV server: '.$exception->getMessage(), 0, $exception);
        }

        if (401 === $status || 403 === $status) {
            throw $this->credential_error($status);
        }

        // Symfony lowercases header names. The ETag is stored and resent
        // verbatim, quotes and any W/ prefix included.
        $etag = $response_headers['etag'][0] ?? null;

        return ['status' => $status, 'etag' => $etag];
    }

    private function credential_error(int $status): IntegrationException
    {
        return new IntegrationException('Apple rejected these credentials. iCloud requires an app-specific password, not your normal Apple ID password.', $status);
    }

    /**
     * The ETag is kept, not discarded: a later conditional PUT needs the tag of
     * the version we actually hold, otherwise an edit made on iCloud leaves us
     * with a stale tag and the next local edit dies on a bogus 412.
     *
     * @return array<string, array{etag: ?string, ics: string}>
     */
    private function extract_calendar_data(\DOMXPath $xpath, string $base_url): array
    {
        $events = [];

        foreach ($this->query_elements($xpath, '//d:response') as $response) {
            $href = $this->query_text($xpath, './/d:href', $response);
            $data = $this->query_text($xpath, './/cal:calendar-data', $response);

            if (!$href || !$data) {
                continue;
            }

            $events[$this->resolve_url($base_url, $href)] = [
                'etag' => $this->query_text($xpath, './/d:getetag', $response),
                'ics' => $data,
            ];
        }

        return $events;
    }

    /**
     * DOMXPath::query() returns false on a bad expression and yields nodes that
     * are not necessarily elements, so narrow both here rather than at every
     * call site.
     *
     * @return list<\DOMElement>
     */
    private function query_elements(\DOMXPath $xpath, string $expression, ?\DOMElement $context = null): array
    {
        $nodes = $xpath->query($expression, $context);

        if (false === $nodes) {
            return [];
        }

        $elements = [];

        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function query_text(\DOMXPath $xpath, string $expression, ?\DOMElement $context = null): ?string
    {
        $element = $this->query_elements($xpath, $expression, $context)[0] ?? null;

        if (!$element) {
            return null;
        }

        $text = trim($element->textContent);

        return '' !== $text ? $text : null;
    }

    private function request(string $method, string $url, string $username, string $password, string $body, string $depth): \DOMXPath
    {
        try {
            $response = $this->http_client->request($method, $url, [
                'auth_basic' => [$username, $password],
                'headers' => [
                    'Depth' => $depth,
                    'Content-Type' => 'application/xml; charset=utf-8',
                ],
                'body' => $body,
            ]);

            $content = $response->getContent();
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            if (401 === $status || 403 === $status) {
                throw $this->credential_error($status);
            }

            throw new IntegrationException(\sprintf('CalDAV request failed with status %d.', $status), $status, $exception);
        } catch (ExceptionInterface $exception) {
            throw new IntegrationException('Could not reach the CalDAV server: '.$exception->getMessage(), 0, $exception);
        }

        $document = new \DOMDocument();

        if (!@$document->loadXML($content)) {
            throw new IntegrationException('CalDAV server returned a malformed response.');
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('d', self::NS_DAV);
        $xpath->registerNamespace('cal', self::NS_CALDAV);

        return $xpath;
    }

    /**
     * Hrefs come back as either absolute URLs or server-root paths, and iCloud
     * moves users onto per-shard hosts, so every href has to be re-based.
     */
    private function resolve_url(string $base, string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($base);

        if (!isset($parts['scheme'], $parts['host'])) {
            throw new IntegrationException(\sprintf('Cannot resolve CalDAV href against "%s".', $base));
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.'/'.ltrim($href, '/');
    }

    private function to_path(string $url): string
    {
        return parse_url($url, \PHP_URL_PATH) ?: $url;
    }
}
