<?php

trait AhBehavior
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function each(callable $callback): void
    {
        foreach ($this->items as $item) {
            $callback($item);
        }
    }

    public function get(string $path, $default = null)
    {
        $segments = explode('.', $path);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return is_array($value) ? new self($value) : $value;
    }

    public function getAll(): array
    {
        return $this->items;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
            return;
        }

        $this->items[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }
}

if (PHP_VERSION_ID >= 80000) {
    eval(<<<'PHP'
class ah implements ArrayAccess
{
    use AhBehavior;

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }
}
PHP);
} else {
    class ah implements ArrayAccess
    {
        use AhBehavior;

        public function offsetGet($offset)
        {
            return $this->items[$offset] ?? null;
        }
    }
}

class AttributeModel
{
    public const TABLE_NAME = 'attributes';
}

class TextHelper
{
    public static function isEqual($left, $right): bool
    {
        return (string) $left === (string) $right;
    }
}

class MoyskladApp
{
    private MockJsonApi $jsonApi;

    public function __construct(MockJsonApi $jsonApi)
    {
        $this->jsonApi = $jsonApi;
    }

    public function getJsonApi(): MockJsonApi
    {
        return $this->jsonApi;
    }
}

class MockJsonApi
{
    private array $invoiceRows;
    public array $sentEntities = [];

    public function __construct(array $invoiceRows)
    {
        $this->invoiceRows = $invoiceRows;
    }

    public function getEntityRows(string $entity, array $params): array
    {
        return $this->invoiceRows;
    }

    public function sendEntity(string $entity, array $payload): void
    {
        $this->sentEntities[$entity] = $payload;
    }
}

require_once dirname(__DIR__) . '/SyncCommand.php';
