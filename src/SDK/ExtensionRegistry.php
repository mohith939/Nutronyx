<?php
declare(strict_types=1);

namespace Nutronyx\SDK;

class ExtensionRegistry
{
    private static ?ExtensionRegistry $instance = null;
    /** @var array<string, array<callable>> */
    private array $slots = [];
    /** @var array<string, array<callable>> */
    private array $filters = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerSlot(string $slotName, callable $renderer): void
    {
        $this->slots[$slotName][] = $renderer;
    }

    public function renderSlot(string $slotName, array $context = []): string
    {
        if (empty($this->slots[$slotName])) {
            return '';
        }

        $output = '';
        foreach ($this->slots[$slotName] as $renderer) {
            try {
                $result = $renderer($context);
                if (is_string($result)) {
                    $output .= $result;
                }
            } catch (\Throwable $e) {
                error_log("Error executing slot '{$slotName}': " . $e->getMessage());
            }
        }
        return $output;
    }

    public function registerFilter(string $filterName, callable $filter): void
    {
        $this->filters[$filterName][] = $filter;
    }

    public function applyFilter(string $filterName, mixed $value, array $context = []): mixed
    {
        if (empty($this->filters[$filterName])) {
            return $value;
        }

        foreach ($this->filters[$filterName] as $filter) {
            try {
                $value = $filter($value, $context);
            } catch (\Throwable $e) {
                error_log("Error applying filter '{$filterName}': " . $e->getMessage());
            }
        }
        return $value;
    }

    public function getSlots(): array
    {
        return array_keys($this->slots);
    }
}
