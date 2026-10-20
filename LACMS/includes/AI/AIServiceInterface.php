<?php
declare(strict_types=1);

interface AIServiceInterface
{
    public function healthCheck(): array;
    public function analyzePriority(array $context): array;
    public function adviseSchedule(array $context): array;
    public function draftSynchronizationBrief(array $context): array;
}
