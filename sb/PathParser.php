<?php

declare(strict_types=1);

/**
 * Parse request path for /sb/<app>/<version>/ convention.
 *
 * Route prefix: /sb/<app>/{v1,v2,v3,...}/ — strip and return app slug,
 * version, and inner path for matching.
 *
 * @link docs/endpoint-schema.md Path and app slug conventions
 */

namespace Switchboard\Runtime;

final class PathParser
{
    /** @var string */
    private const PREFIX = '/sb/';

    /**
     * If path starts with /sb/<app>/<version>/, extract app slug, version, and inner path.
     *
     * Otherwise return null (no prefix) and matching uses full path.
     *
     * @param string $path Request path (e.g. /sb/minimal/v1/health).
     * @return array{app_slug: string, version: string, path: string}|null Parsed prefix or null.
     */
    public static function parsePrefix(string $path): ?array
    {
        $path = self::normalizePath($path);
        if (strpos($path, self::PREFIX) !== 0) {
            return null;
        }

        $rest = substr($path, strlen(self::PREFIX));
        $parts = array_filter(explode('/', $rest), fn($p) => $p !== '');
        $parts = array_values($parts);

        if (count($parts) < 2) {
            return null;
        }

        $appSlug = $parts[0];
        $version = $parts[1];
        $innerPath = count($parts) > 2 ? '/' . implode('/', array_slice($parts, 2)) : '/';

        return [
            'app_slug' => $appSlug,
            'version' => $version,
            'path' => $innerPath,
        ];
    }

    /**
     * Parse a deploy-time mounted app API path.
     *
     * Example: /foo/api/health with app=foo, mount=/foo, api=/api returns
     * app_slug=foo and path=/health. Static frontend paths such as /foo stay
     * owned by Nginx and should not be forwarded here.
     *
     * @return array{app_slug: string, version: string, path: string}|null
     */
    public static function parseMount(
        string $path,
        ?string $appSlug,
        ?string $mountPath,
        ?string $apiPrefix = '/api'
    ): ?array {
        $appSlug = trim((string)$appSlug);
        if ($appSlug === '' || $mountPath === null || $mountPath === '') {
            return null;
        }

        $path = self::normalizePath($path);
        $mountPath = self::normalizePath($mountPath);
        $apiPrefix = self::normalizePath($apiPrefix ?? '/api');
        $targetPrefix = self::normalizePath($mountPath . '/' . trim($apiPrefix, '/'));

        if ($path !== $targetPrefix && strpos($path, $targetPrefix . '/') !== 0) {
            return null;
        }

        $innerPath = substr($path, strlen($targetPrefix));
        $innerPath = $innerPath === false || $innerPath === '' ? '/' : self::normalizePath($innerPath);

        return [
            'app_slug' => $appSlug,
            'version' => 'mounted',
            'path' => $innerPath,
        ];
    }

    /**
     * Normalize path: ensure leading slash; remove trailing slash (match Node behavior).
     *
     * @param string $path Raw request path.
     * @return string Normalized path (leading slash, no trailing slash except for root).
     */
    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }
}
