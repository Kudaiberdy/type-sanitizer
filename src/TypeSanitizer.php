<?php

declare(strict_types=1);

namespace TypeSanitizer;

use ReflectionClass;
use ReflectionException;
use TypeSanitizer\Contracts\Sanitizer as SanitizerContract;
use TypeSanitizer\Exceptions\InvalidJsonKeyException;
use TypeSanitizer\Exceptions\InvalidJsonStringException;

final class TypeSanitizer implements SanitizerContract
{
    /**
     * @template T
     *
     * @param string|array          $data
     * @param class-string<T>|array $specification
     * @param bool                  $nullOnFailure
     *
     * @return T|array<T>|array
     *
     * @throws InvalidJsonKeyException
     * @throws InvalidJsonStringException
     * @throws ReflectionException
     */
    public function sanitize(string|array $data, string|array $specification, bool $nullOnFailure = false): array|object
    {
        $convertedData = $this->convertDataToArray($data);
        $convertedSpec = $this->convertSpecificationToArray($specification);
        $sanitizedData = [];

        if ($this->isList($convertedData)) {
            foreach ($data as $item) {
                $sanitizedData[] = $this->sanitizeData($item, $convertedSpec);
            }
        } else {
            $sanitizedData = $this->sanitizeData($convertedData, $convertedSpec);
        }

        if (!$nullOnFailure) {
            array_walk_recursive(
                $sanitizedData,
                fn($item) => $item ?? throw new InvalidJsonKeyException("Invalid field $item")
            );
        }

        if (is_string($specification)) {
            if ($this->isList($sanitizedData)) {
                return array_map(fn(array $item): object => $this->buildObject($specification, $item), $sanitizedData);
            }
            return $this->buildObject($specification, $sanitizedData);
        }

        return $sanitizedData;
    }

    /**
     * @param string|array $data
     *
     * @return array
     * @throws InvalidJsonStringException
     */
    private function convertDataToArray(string|array $data): array
    {
        if (is_string($data)) {
            return $this->convertJsonToArray($data);
        }

        return $data;
    }

    /**
     * @template T
     *
     * @param class-string<T>|array $specification
     *
     * @return array
     * @throws ReflectionException
     */
    private function convertSpecificationToArray(array|string $specification): array
    {
        $convertedSpec = [];

        if (is_string($specification)) {
            $reflect = new ReflectionClass($specification);

            foreach ($reflect->getProperties() as $property) {
                $convertedSpec[$property->getName()] = $this->convertSpecificationToFilter($property->getType()->getName());
            }

            return $convertedSpec;
        }

        foreach ($specification as $type => $spec) {
            $convertedSpec[$type] = $this->convertSpecificationToFilter($spec);
        }

        return $convertedSpec;
    }

    /**
     * @param string $data
     *
     * @return array
     * @throws InvalidJsonStringException
     */
    private function convertJsonToArray(string $data): array
    {
        return json_decode($data, true) ?? throw new InvalidJsonStringException();
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function isList(array $data): bool
    {
        if ($data === []) {
            return true;
        }

        return array_keys($data) === range(0, count($data) - 1);
    }

    /**
     * @param array $data
     * @param array $specification
     *
     * @return array
     */
    private function sanitizeData(array $data, array $specification): array
    {
        return filter_var_array($data, $specification);
    }

    /**
     * @param string $type
     *
     * @return string|array|null
     */
    public function convertSpecificationToFilter(string $type): string|array|null
    {
        return match ($type) {
            'string'      => 'htmlspecialchars',
            'bool'        => [
                'filter' => FILTER_VALIDATE_BOOL,
                'flags'  => FILTER_NULL_ON_FAILURE
            ],
            'int'         => [
                'filter' => FILTER_VALIDATE_INT,
                'flags'  => FILTER_NULL_ON_FAILURE
            ],
            'float'       => [
                'filter' => FILTER_VALIDATE_FLOAT,
                'flags'  => FILTER_NULL_ON_FAILURE
            ],
            'phoneNumber' => [
                'filter'  => FILTER_CALLBACK,
                'options' => [$this, 'sanitizePhoneNumber'],
            ],
            'int[]'       => [
                'filter' => FILTER_VALIDATE_INT,
                'flags'  => FILTER_REQUIRE_ARRAY,
            ],
            default       => null,
        };
    }

    /**
     * @param string $phoneNumber
     *
     * @return string|null
     */
    private function sanitizePhoneNumber(string $phoneNumber): string|null
    {
        $phoneNumber = str_replace([' ','+', '(', ')', '-'], '', $phoneNumber);

        if (!preg_match('/^(?:7|8)(?:[1-9]\d{2})\d{7}$/', $phoneNumber)) {
            return null;
        }

        return '+7' . substr($phoneNumber, 1);
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     * @param array           $data
     *
     * @return T
     * @throws ReflectionException
     */
    private function buildObject(string $className, array $data): object
    {
        $obj     = new $className();
        $reflect = new ReflectionClass($obj);

        foreach ($data as $paramName => $paramValue) {
            $property = $reflect->getProperty($paramName);
            if ($property->isPublic()) {
                @$obj->$paramName = $paramValue;
            }
        }

        return $obj;
    }
}
