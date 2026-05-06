<?php

declare(strict_types=1);

/**
 * Normalized request shape per docs/php-app-contract.md.
 *
 * Implements SwitchboardRequest for backend app type-hinting. Server-agnostic
 * (no $_SERVER / Apache/nginx specifics exposed to handlers).
 *
 * @link docs/php-app-contract.md PHP app contract
 */

namespace Switchboard\Runtime;

final class NormalizedRequest implements SwitchboardRequest
{
    /** @var string */
    public readonly string $host;

    /** @var string */
    public readonly string $path;

    /** @var string */
    public readonly string $method;

    /** @var array<string, string|array<string>> */
    public readonly array $query;

    /** @var array<string, string> */
    public readonly array $headers;

    /** @var array<string, string> */
    public readonly array $cookies;

    /** @var string|null */
    public readonly ?string $body;

    /** @var array<string, string|array<string>> */
    public readonly array $form;

    /** @var mixed */
    public readonly mixed $json;

    /** @var string|null */
    public readonly ?string $contentType;

    /** @var string|null */
    public readonly ?string $remoteAddr;

    /** @var string|null */
    public readonly ?string $forwardedFor;

    /** @var array<string, string> */
    public readonly array $pathParams;

    /**
     * Build a normalized request instance.
     *
     * @param string                        $host       Request host.
     * @param string                        $path       Request path.
     * @param string                        $method     HTTP method.
     * @param array<string, string|array<string>> $query      Query parameters.
     * @param array<string, string>        $headers    Request headers (lowercased keys).
     * @param array<string, string>        $cookies    Request cookies.
     * @param string|null                  $body       Raw body.
     * @param string|null                  $contentType Content-Type header value.
     * @param string|null                  $remoteAddr Remote address.
     * @param string|null                  $forwardedFor X-Forwarded-For value.
     */
    public function __construct(
        string $host,
        string $path,
        string $method,
        array $query = [],
        array $headers = [],
        array $cookies = [],
        ?string $body = null,
        ?string $contentType = null,
        ?string $remoteAddr = null,
        ?string $forwardedFor = null,
        array $pathParams = [],
        array $form = [],
        mixed $json = null
    ) {
        $this->host = $host;
        $this->path = $path;
        $this->method = $method;
        $this->query = $query;
        $this->headers = $headers;
        $this->cookies = $cookies;
        $this->body = $body;
        $this->form = $form;
        $this->json = $json;
        $this->contentType = $contentType;
        $this->remoteAddr = $remoteAddr;
        $this->forwardedFor = $forwardedFor;
        $this->pathParams = $pathParams;
    }

    /** @inheritDoc */
    public function getHost(): string
    {
        return $this->host;
    }

    /** @inheritDoc */
    public function getPath(): string
    {
        return $this->path;
    }

    /** @inheritDoc */
    public function getMethod(): string
    {
        return $this->method;
    }

    /** @inheritDoc */
    public function getQuery(): array
    {
        return $this->query;
    }

    /** @inheritDoc */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @inheritDoc */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /** @inheritDoc */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * @return array<string, string|array<string>>
     */
    public function getForm(): array
    {
        return $this->form;
    }

    public function getJson(): mixed
    {
        return $this->json;
    }

    /** @inheritDoc */
    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    /** @inheritDoc */
    public function getRemoteAddr(): ?string
    {
        return $this->remoteAddr;
    }

    /** @inheritDoc */
    public function getForwardedFor(): ?string
    {
        return $this->forwardedFor;
    }

    /**
     * @return array<string, string>
     */
    public function getPathParams(): array
    {
        return $this->pathParams;
    }

    /**
     * @param array<string, string> $pathParams
     */
    public function withPathParams(array $pathParams): self
    {
        return new self(
            $this->host,
            $this->path,
            $this->method,
            $this->query,
            $this->headers,
            $this->cookies,
            $this->body,
            $this->contentType,
            $this->remoteAddr,
            $this->forwardedFor,
            $pathParams,
            $this->form,
            $this->json
        );
    }

    /**
     * Build a normalized request from PHP superglobals.
     *
     * Extracts host, path, method, query, headers, body, and client address
     * in a server-agnostic way (works behind Apache, nginx, CLI).
     *
     * @return self Normalized request instance.
     */
    public static function fromGlobals(): self
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = $path !== null && $path !== false ? $path : '/';
        $path = $path === '' ? '/' : $path;

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $query = [];
        $queryString = parse_url($requestUri, PHP_URL_QUERY);
        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $parsed);
            foreach ($parsed as $k => $v) {
                $query[$k] = is_array($v) ? $v : (string) $v;
            }
        }

        $cookies = isset($_COOKIE) && is_array($_COOKIE) ? array_map('strval', $_COOKIE) : [];

        $headers = [];
        if (function_exists('getallheaders')) {
            $raw = getallheaders();
            if (is_array($raw)) {
                foreach ($raw as $name => $value) {
                    $headers[strtolower($name)] = (string) $value;
                }
            }
        } else {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0 && is_string($value)) {
                    $name = strtolower(str_replace('_', '-', substr($key, 5)));
                    $headers[$name] = $value;
                }
            }
        }

        $body = file_get_contents('php://input');
        $body = $body !== false ? $body : null;
        $contentType = $headers['content-type'] ?? null;
        $form = self::parseForm($body, $contentType);
        $json = self::parseJson($body, $contentType);
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $forwardedFor = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : null;

        return new self(
            $host,
            $path,
            $method,
            $query,
            $headers,
            $cookies,
            $body,
            $contentType,
            $remoteAddr,
            $forwardedFor,
            [],
            $form,
            $json
        );
    }

    /**
     * @return array<string, string|array<string>>
     */
    public static function parseForm(?string $body, ?string $contentType): array
    {
        $contentType = strtolower((string)$contentType);
        if (str_contains($contentType, 'multipart/form-data')) {
            return isset($_POST) && is_array($_POST) ? array_map(fn($v) => is_array($v) ? array_map('strval', $v) : (string)$v, $_POST) : [];
        }
        if (!str_contains($contentType, 'application/x-www-form-urlencoded') || $body === null || $body === '') {
            return [];
        }
        parse_str($body, $parsed);
        $form = [];
        foreach ($parsed as $key => $value) {
            $form[$key] = is_array($value) ? array_map('strval', $value) : (string)$value;
        }
        return $form;
    }

    public static function parseJson(?string $body, ?string $contentType): mixed
    {
        if ($body === null || $body === '' || !str_contains(strtolower((string)$contentType), 'json')) {
            return null;
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
