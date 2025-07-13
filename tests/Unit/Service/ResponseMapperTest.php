<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Service;

use Freema\N8nBundle\Service\ResponseMapper;
use Freema\N8nBundle\Tests\Fixtures\TestResponse;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freema\N8nBundle\Service\ResponseMapper
 */
class ResponseMapperTest extends TestCase
{
    private ResponseMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ResponseMapper();
    }

    public function testMapToClassWithConstructorParameters(): void
    {
        $data = [
            'status' => 'success',
            'message' => 'Test message',
            'timestamp' => 1234567890,
        ];

        $result = $this->mapper->mapToClass($data, TestResponse::class);

        $this->assertInstanceOf(TestResponse::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals('Test message', $result->message);
        $this->assertEquals(1234567890, $result->timestamp);
    }

    public function testMapToClassWithMissingRequiredParameter(): void
    {
        $data = [
            'status' => 'success',
            // missing 'message' and 'timestamp'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required parameter 'message' not found in response data");

        $this->mapper->mapToClass($data, TestResponse::class);
    }

    public function testMapToClassWithOptionalParameters(): void
    {
        $testClass = new class('test') {
            public function __construct(
                public readonly string $required,
                public readonly ?string $optional = null,
            ) {
            }
        };

        $data = ['required' => 'test'];

        $result = $this->mapper->mapToClass($data, $testClass::class);

        $this->assertEquals('test', $result->required);
        $this->assertNull($result->optional);
    }

    public function testMapToClassWithPropertySetting(): void
    {
        $testClass = new class {
            public string $name;
            public int $age;
        };

        $data = [
            'name' => 'John Doe',
            'age' => 30,
        ];

        $result = $this->mapper->mapToClass($data, $testClass::class);

        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals(30, $result->age);
    }

    public function testMapToClassWithNonExistentClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class NonExistentClass does not exist');

        $this->mapper->mapToClass([], 'NonExistentClass');
    }

    public function testMapToClassWithExtraDataIsIgnored(): void
    {
        $data = [
            'status' => 'success',
            'message' => 'Test message',
            'timestamp' => 1234567890,
            'extra_field' => 'ignored',
        ];

        $result = $this->mapper->mapToClass($data, TestResponse::class);

        $this->assertInstanceOf(TestResponse::class, $result);
        $this->assertEquals('success', $result->status);
    }
}
