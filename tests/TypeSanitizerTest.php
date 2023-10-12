<?php

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeSanitizer\TypeSanitizer;

final class TypeSanitizerTest extends TestCase
{
    #[DataProvider('dataProviderForSuccessSanitizing')]
    public function testSuccessSanitizing(mixed $data, mixed $specification, mixed $expectingData): void
    {
        $sanitizer = new TypeSanitizer();
        $this->assertEquals($sanitizer->sanitize($data, $specification), $expectingData);
    }

    public static function dataProviderForSuccessSanitizing(): array
    {
        return [
            'testDataAsString' => [
                'data'          => [
                    'fieldOneInt'     => '123',
                    'fieldTwoInt'     => 123,
                    'fieldTreeString' => '1231',
                    'fieldFourFloat' => '1231.1233',
                ],
                'specification' => [
                    'fieldOneInt'     => 'int',
                    'fieldTwoInt'     => 'int',
                    'fieldTreeString' => 'string',
                    'fieldFourFloat' => 'float',
                ],
                'expectingData' => [
                    'fieldOneInt'     => 123,
                    'fieldTwoInt'     => 123,
                    'fieldTreeString' => '1231',
                    'fieldFourFloat' => 1231.1233,
                ],
            ]
        ];
    }
}
