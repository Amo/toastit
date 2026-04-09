<?php

namespace App\Admin;

use App\Entity\AiPrompt;
use App\Entity\AiPromptVersion;
use App\Entity\User;
use App\Repository\AiPromptRepository;
use App\Repository\AiPromptVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RootPromptService
{
    public function __construct(
        private readonly AiPromptRepository $promptRepository,
        private readonly AiPromptVersionRepository $promptVersionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{
     *   code: string,
     *   label: string,
     *   description: ?string,
     *   availableVariables: list<array{name: string, description: string, example: string}>,
     *   availableUserVariables: list<array{name: string, description: string, example: string}>,
      *   latestVersionNumber: int|null,
      *   latestChangedAt: string|null,
      *   latestChangedBy: ?string
     * }>
     */
    public function listPrompts(): array
    {
        $prompts = $this->promptRepository->findAllOrderedByLabel();

        return array_map(function (AiPrompt $prompt): array {
            $latest = $this->promptVersionRepository->findLatestForPrompt($prompt);

            return [
                'code' => $prompt->getCode(),
                'label' => $prompt->getLabel(),
                'description' => $prompt->getDescription(),
                'availableVariables' => $prompt->getAvailableVariables(),
                'availableUserVariables' => $prompt->getAvailableUserVariables(),
                'latestVersionNumber' => $latest?->getVersionNumber(),
                'latestChangedAt' => $latest?->getChangedAt()->format(\DateTimeInterface::ATOM),
                'latestChangedBy' => $latest?->getChangedByUser()?->getDisplayName(),
            ];
        }, $prompts);
    }

    /**
     * @return array{
     *   code: string,
     *   label: string,
     *   description: ?string,
     *   availableVariables: list<array{name: string, description: string, example: string}>,
     *   availableUserVariables: list<array{name: string, description: string, example: string}>,
      *   currentSystemPrompt: string,
     *   currentUserPromptTemplate: string,
      *   currentVersionNumber: int,
     *   versions: list<array{versionNumber: int, changedAt: string, changedBy: ?string, systemPrompt: string, userPromptTemplate: string}>
     * }|null
     */
    public function getPrompt(string $code): ?array
    {
        $prompt = $this->promptRepository->findOneByCode($code);

        if (!$prompt instanceof AiPrompt) {
            return null;
        }

        $versions = $this->promptVersionRepository->findAllForPrompt($prompt);
        $latestVersion = $versions[0] ?? null;

        if (!$latestVersion instanceof AiPromptVersion) {
            return null;
        }

        return [
            'code' => $prompt->getCode(),
            'label' => $prompt->getLabel(),
            'description' => $prompt->getDescription(),
            'availableVariables' => $prompt->getAvailableVariables(),
            'availableUserVariables' => $prompt->getAvailableUserVariables(),
            'currentSystemPrompt' => $latestVersion->getSystemPrompt(),
            'currentUserPromptTemplate' => $latestVersion->getUserPromptTemplate(),
            'currentVersionNumber' => $latestVersion->getVersionNumber(),
            'versions' => array_map(static fn (AiPromptVersion $version): array => [
                'versionNumber' => $version->getVersionNumber(),
                'changedAt' => $version->getChangedAt()->format(\DateTimeInterface::ATOM),
                'changedBy' => $version->getChangedByUser()?->getDisplayName(),
                'systemPrompt' => $version->getSystemPrompt(),
                'userPromptTemplate' => $version->getUserPromptTemplate(),
            ], $versions),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createPromptVersion(string $code, string $systemPrompt, string $userPromptTemplate, User $changedBy): ?array
    {
        $prompt = $this->promptRepository->findOneByCode($code);

        if (!$prompt instanceof AiPrompt) {
            return null;
        }

        $latestVersion = $this->promptVersionRepository->findLatestForPrompt($prompt);
        $nextVersionNumber = ($latestVersion?->getVersionNumber() ?? 0) + 1;

        $version = (new AiPromptVersion())
            ->setPrompt($prompt)
            ->setChangedByUser($changedBy)
            ->setVersionNumber($nextVersionNumber)
            ->setSystemPrompt($systemPrompt)
            ->setUserPromptTemplate($userPromptTemplate)
            ->setChangedAt(new \DateTimeImmutable());

        $prompt->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($version);
        $this->entityManager->flush();

        return $this->getPrompt($code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function rollbackPromptVersion(string $code, int $targetVersionNumber, User $changedBy): ?array
    {
        $prompt = $this->promptRepository->findOneByCode($code);

        if (!$prompt instanceof AiPrompt) {
            return null;
        }

        $targetVersion = null;
        foreach ($this->promptVersionRepository->findAllForPrompt($prompt) as $version) {
            if ($version->getVersionNumber() === $targetVersionNumber) {
                $targetVersion = $version;
                break;
            }
        }

        if (!$targetVersion instanceof AiPromptVersion) {
            return null;
        }

        return $this->createPromptVersion(
            $code,
            $targetVersion->getSystemPrompt(),
            $targetVersion->getUserPromptTemplate(),
            $changedBy,
        );
    }
}
