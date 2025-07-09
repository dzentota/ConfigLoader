<?php

declare(strict_types=1);

namespace dzentota\ConfigLoader\Tests\Source;

use PHPUnit\Framework\TestCase;
use dzentota\ConfigLoader\Source\ArraySource;
use dzentota\ConfigLoader\Exception\SourceException;

class ArraySourceTest extends TestCase
{
    public function testConstructor(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $source = new ArraySource($data, 50, 'Test Source');
        
        $this->assertEquals(50, $source->getPriority());
        $this->assertEquals('Test Source', $source->getName());
        $this->assertEquals($data, $source->load());
    }

    public function testConstructorWithDefaults(): void
    {
        $data = ['key1' => 'value1'];
        $source = new ArraySource($data);
        
        $this->assertEquals(10, $source->getPriority()); // Default priority
        $this->assertEquals('Array Source', $source->getName()); // Default name
        $this->assertEquals($data, $source->load());
    }

    public function testLoad(): void
    {
        $data = [
            'port' => '8080',
            'debug' => 'true',
            'database.host' => 'localhost'
        ];
        
        $source = new ArraySource($data);
        $loaded = $source->load();
        
        $this->assertEquals($data, $loaded);
        $this->assertSame($data, $loaded); // Should return exact same array
    }

    public function testLoadWithEmptyArray(): void
    {
        $source = new ArraySource([]);
        $loaded = $source->load();
        
        $this->assertIsArray($loaded);
        $this->assertEmpty($loaded);
    }

    public function testHas(): void
    {
        $data = ['existing_key' => 'value', 'another_key' => null];
        $source = new ArraySource($data);
        
        $this->assertTrue($source->has('existing_key'));
        $this->assertFalse($source->has('another_key')); // null values return false with isset()
        $this->assertFalse($source->has('non_existent_key'));
    }

    public function testGet(): void
    {
        $data = [
            'string_value' => 'test',
            'int_value' => 42,
            'bool_value' => true,
            'array_value' => ['nested' => 'value']
        ];
        
        $source = new ArraySource($data);
        
        $this->assertEquals('test', $source->get('string_value'));
        $this->assertEquals(42, $source->get('int_value'));
        $this->assertTrue($source->get('bool_value'));
        $this->assertEquals(['nested' => 'value'], $source->get('array_value'));
    }

    public function testGetThrowsExceptionForNonExistentKey(): void
    {
        $source = new ArraySource(['key1' => 'value1']);
        
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Configuration key "non_existent" not found in array source');
        
        $source->get('non_existent');
    }

    public function testSetData(): void
    {
        $initialData = ['key1' => 'value1'];
        $newData = ['key2' => 'value2', 'key3' => 'value3'];
        
        $source = new ArraySource($initialData);
        $source->setData($newData);
        
        $this->assertEquals($newData, $source->load());
        $this->assertFalse($source->has('key1')); // Old data should be replaced
        $this->assertTrue($source->has('key2'));
        $this->assertTrue($source->has('key3'));
    }

    public function testMergeData(): void
    {
        $initialData = ['key1' => 'value1', 'key2' => 'old_value'];
        $mergeData = ['key2' => 'new_value', 'key3' => 'value3'];
        
        $source = new ArraySource($initialData);
        $source->mergeData($mergeData);
        
        $expected = [
            'key1' => 'value1',
            'key2' => 'new_value', // Should be overridden
            'key3' => 'value3'
        ];
        
        $this->assertEquals($expected, $source->load());
    }

    public function testSet(): void
    {
        $source = new ArraySource(['existing' => 'value']);
        
        $source->set('new_key', 'new_value');
        $source->set('existing', 'updated_value');
        
        $this->assertEquals('new_value', $source->get('new_key'));
        $this->assertEquals('updated_value', $source->get('existing'));
    }

    public function testSetWithDifferentTypes(): void
    {
        $source = new ArraySource([]);
        
        $source->set('string', 'text');
        $source->set('int', 42);
        $source->set('bool', true);
        $source->set('array', ['nested' => 'value']);
        
        $this->assertEquals('text', $source->get('string'));
        $this->assertEquals(42, $source->get('int'));
        $this->assertTrue($source->get('bool'));
        $this->assertEquals(['nested' => 'value'], $source->get('array'));
    }

    public function testRemove(): void
    {
        $source = new ArraySource(['key1' => 'value1', 'key2' => 'value2']);
        
        $this->assertTrue($source->has('key1'));
        $source->remove('key1');
        $this->assertFalse($source->has('key1'));
        
        // Should not throw exception for non-existent key
        $source->remove('non_existent');
        $this->assertFalse($source->has('non_existent'));
    }

    public function testGetKeys(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];
        $source = new ArraySource($data);
        
        $keys = $source->getKeys();
        
        $this->assertIsArray($keys);
        $this->assertCount(3, $keys);
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('key3', $keys);
    }

    public function testGetKeysWithEmptyArray(): void
    {
        $source = new ArraySource([]);
        $keys = $source->getKeys();
        
        $this->assertIsArray($keys);
        $this->assertEmpty($keys);
    }

    public function testIsEmpty(): void
    {
        $emptySource = new ArraySource([]);
        $this->assertTrue($emptySource->isEmpty());
        
        $nonEmptySource = new ArraySource(['key' => 'value']);
        $this->assertFalse($nonEmptySource->isEmpty());
        
        // Test after clearing
        $nonEmptySource->clear();
        $this->assertTrue($nonEmptySource->isEmpty());
    }

    public function testCount(): void
    {
        $source = new ArraySource(['key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals(2, $source->count());
        
        $source->set('key3', 'value3');
        $this->assertEquals(3, $source->count());
        
        $source->remove('key1');
        $this->assertEquals(2, $source->count());
        
        $source->clear();
        $this->assertEquals(0, $source->count());
    }

    public function testClear(): void
    {
        $source = new ArraySource(['key1' => 'value1', 'key2' => 'value2']);
        
        $this->assertFalse($source->isEmpty());
        $this->assertEquals(2, $source->count());
        
        $source->clear();
        
        $this->assertTrue($source->isEmpty());
        $this->assertEquals(0, $source->count());
        $this->assertEmpty($source->load());
    }

    public function testGetNameAfterConstruction(): void
    {
        $source = new ArraySource([], 10, 'Custom Name');
        $this->assertEquals('Custom Name', $source->getName());
    }

    public function testSourceExceptionContainsCorrectDetails(): void
    {
        $source = new ArraySource(['key1' => 'value1']);
        
        try {
            $source->get('missing_key');
            $this->fail('Expected SourceException was not thrown');
        } catch (SourceException $e) {
            $this->assertEquals('missing_key', $e->getSourceName());
            $this->assertEquals('array', $e->getSourceType());
            $this->assertStringContainsString('Configuration key "missing_key" not found in array source', $e->getMessage());
        }
    }

    public function testComplexDataTypes(): void
    {
        $complexData = [
            'nested_array' => [
                'level1' => [
                    'level2' => 'deep_value'
                ]
            ],
            'numeric_array' => [1, 2, 3, 4],
            'mixed_array' => ['string', 42, true, null],
            'object_like' => [
                'property1' => 'value1',
                'property2' => 'value2'
            ]
        ];
        
        $source = new ArraySource($complexData);
        
        $this->assertEquals($complexData['nested_array'], $source->get('nested_array'));
        $this->assertEquals($complexData['numeric_array'], $source->get('numeric_array'));
        $this->assertEquals($complexData['mixed_array'], $source->get('mixed_array'));
        $this->assertEquals($complexData['object_like'], $source->get('object_like'));
    }

    public function testPriorityImmutable(): void
    {
        $source = new ArraySource([], 50);
        $this->assertEquals(50, $source->getPriority());
        
        // Priority should remain the same after operations
        $source->set('key', 'value');
        $this->assertEquals(50, $source->getPriority());
        
        $source->mergeData(['key2' => 'value2']);
        $this->assertEquals(50, $source->getPriority());
    }

    public function testNameImmutable(): void
    {
        $source = new ArraySource([], 10, 'Original Name');
        $this->assertEquals('Original Name', $source->getName());
        
        // Name should remain the same after operations
        $source->set('key', 'value');
        $this->assertEquals('Original Name', $source->getName());
        
        $source->setData(['new' => 'data']);
        $this->assertEquals('Original Name', $source->getName());
    }
} 