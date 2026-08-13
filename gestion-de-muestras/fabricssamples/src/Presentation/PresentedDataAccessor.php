<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Presentation;

final class PresentedDataAccessor
{
    public function get($presented, string $key, $default = null)
    {
        if (is_array($presented)) {
            return array_key_exists($key, $presented) ? $presented[$key] : $default;
        }
        if ($presented instanceof \ArrayAccess && $presented->offsetExists($key)) {
            return $presented->offsetGet($key);
        }
        if (is_object($presented) && isset($presented->{$key})) {
            return $presented->{$key};
        }

        return $default;
    }

    public function set(&$presented, string $key, $value): void
    {
        if (is_array($presented)) {
            $presented[$key] = $value;
            return;
        }
        if ($presented instanceof \ArrayAccess) {
            $presented->offsetSet($key, $value);
            return;
        }
        if (is_object($presented)) {
            $presented->{$key} = $value;
        }
    }
}
