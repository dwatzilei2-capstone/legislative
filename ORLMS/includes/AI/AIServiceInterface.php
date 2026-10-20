<?php
declare(strict_types=1);

interface AIServiceInterface
{
    public function healthCheck(): array;
    public function classifyDocument(array $context): array;
    public function reviewDraft(array $context): array;
}
