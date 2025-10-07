<?php

namespace App\RuleEngine;

final class RuleEvaluationResult
{
    /** @param array<string, array<string, mixed>> $triggered */
    public function __construct(private array $triggered)
    {
    }

    public function hasTriggered(): bool
    {
        return !empty($this->triggered);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getTriggeredRules(): array
    {
        return $this->triggered;
    }
}
