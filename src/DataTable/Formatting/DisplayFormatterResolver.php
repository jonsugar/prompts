<?php

namespace Laravel\Prompts\DataTable\Formatting;

class DisplayFormatterResolver
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function resolve(array $configuration): ?DisplayFormatter
    {
        $display = $configuration['display'] ?? null;

        if ($display === null) {
            return null;
        }

        if (is_callable($display)) {
            return new CallableDisplayFormatter($display);
        }

        if (is_string($display)) {
            if ($this->looksLikePrintfPattern($display)) {
                return new PrintfDisplayFormatter($display);
            }
            $formatter = $this->normalizeDisplayFormatterName($display);

            return match ($formatter) {
                'printf' => new PrintfDisplayFormatter((string) ($configuration['pattern'] ?? $configuration['template'] ?? '')),
                default => null,
            };
        }

        if (! is_array($display)) {
            return null;
        }

        $formatter = $this->normalizeDisplayFormatterName(
            (string) ($display['type'] ?? $display['format'] ?? $display['name'] ?? '')
        );

        return match ($formatter) {
            'printf' => new PrintfDisplayFormatter((string) ($display['pattern'] ?? $display['template'] ?? '')),
            default => null,
        };
    }

    protected function normalizeDisplayFormatterName(string $name): string
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            return '';
        }

        return match ($name) {
            'printf', 'sprintf', 'pattern', 'template' => 'printf',
            default => $name,
        };
    }

    protected function looksLikePrintfPattern(string $display): bool
    {
        return str_contains($display, '%');
    }
}
