<?php
declare(strict_types=1);

/**
 * includes/AI/AIServiceInterface.php
 * Interface for AI Services in VQDSS (Voting, Quorum, and Decision Support System).
 */
interface AIServiceInterface
{
    /**
     * Check Ollama daemon health and available models.
     */
    public function healthCheck(): array;

    /**
     * Generate formal legislative decision excerpt, statutory legal basis, and effectivity conditions.
     */
    public function draftDecisionStatement(array $decisionContext): array;

    /**
     * Generate recommended implementation actions for executive departments.
     */
    public function suggestImplementationActions(array $decisionContext): array;

    /**
     * Conduct legislative integrity audit and generate Executive Summary, Findings, and Secretary's Certification.
     */
    public function auditValidationResult(array $validationContext): array;

    /**
     * Advise on statutory voting threshold and procedural rules based on motion title/details under RA 7160.
     */
    public function adviseVotingThreshold(array $votingContext): array;

    /**
     * Generate recount justification memorandum based on tally discrepancy or tie vote.
     */
    public function draftRecountJustification(array $tallyContext): array;
}
