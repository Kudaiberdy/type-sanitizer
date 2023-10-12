<?php

declare(strict_types=1);

namespace TypeSanitizer\Contracts;

interface Sanitizer
{
    /**
     * @template T
     *
     * @param string|array             $data
     * @param class-string<T>|array<T> $specification
     * @param bool                     $nullOnFailure
     *
     * @return T|array<T>|array
     */
    public function sanitize(string|array $data, string|array $specification, bool $nullOnFailure = false): mixed;
}
