<?php

declare(strict_types=1);

namespace OnlineService\Sync;

final class UfMap
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $map = null;

    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $path = __DIR__ . '/config/uf_mapping.php';
        $raw = include $path;
        if (!\is_array($raw)) {
            throw new \RuntimeException('UF mapping config must return array.');
        }

        self::$map = self::normalize($raw);

        return self::$map;
    }

    /**
     * @return array<string, string>
     */
    public static function company(): array
    {
        return self::scope('company');
    }

    /**
     * @return array<string, string>
     */
    public static function contact(): array
    {
        return self::scope('contact');
    }

    public static function get(string $key): string
    {
        $key = \trim($key);
        if ($key === '') {
            throw new \InvalidArgumentException('UF mapping key must not be empty.');
        }

        $parts = \explode('.', $key, 2);
        if (\count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('UF mapping key must be in "scope.name" format.');
        }

        [$scope, $name] = $parts;
        $map = self::scope($scope);
        if (!\array_key_exists($name, $map)) {
            throw new \OutOfBoundsException('Unknown UF mapping key: ' . $key);
        }

        return $map[$name];
    }

    /**
     * @return array<string, string>
     */
    private static function scope(string $scope): array
    {
        $map = self::all();
        if (!\array_key_exists($scope, $map)) {
            throw new \OutOfBoundsException('Unknown UF mapping scope: ' . $scope);
        }

        return $map[$scope];
    }

    /**
     * @param array<mixed> $raw
     * @return array<string, array<string, string>>
     */
    private static function normalize(array $raw): array
    {
        $out = [];
        foreach ($raw as $scope => $values) {
            if (!\is_string($scope) || $scope === '') {
                throw new \RuntimeException('UF mapping scope must be non-empty string.');
            }
            if (!\is_array($values)) {
                throw new \RuntimeException('UF mapping scope "' . $scope . '" must be array.');
            }
            $normalizedScope = [];
            foreach ($values as $name => $field) {
                if (!\is_string($name) || $name === '') {
                    throw new \RuntimeException('UF mapping key in "' . $scope . '" must be non-empty string.');
                }
                if (!\is_string($field) || $field === '') {
                    throw new \RuntimeException('UF mapping value "' . $scope . '.' . $name . '" must be non-empty string.');
                }
                $normalizedScope[$name] = $field;
            }
            $out[$scope] = $normalizedScope;
        }

        return $out;
    }
}
