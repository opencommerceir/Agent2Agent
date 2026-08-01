<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\Language;
use App\Core\Domain\ValueObjects\TenantStatus;
use DateTimeImmutable;

/**
 * Tenant is the isolation boundary of the platform (Decision 011).
 * It is the only Core entity that does not carry a tenant_id.
 */
final class Tenant
{
    public function __construct(
        private readonly ?int $id,
        private string $name,
        private string $slug,
        private TenantStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private Language $defaultLanguage = Language::English,
    ) {
    }

    public static function register(string $name, string $slug): self
    {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            status: TenantStatus::Pending,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function activate(): void
    {
        $this->status = TenantStatus::Active;
    }

    public function suspend(): void
    {
        $this->status = TenantStatus::Suspended;
    }

    /**
     * Phase 4 Stage 4 (i18n) — the tier LanguageDetector falls back to
     * when a request carries neither a ?lang= query parameter nor a
     * recognized Accept-Language header, and the tier an event Listener
     * (no Request at all) uses exclusively.
     */
    public function changeDefaultLanguage(Language $language): void
    {
        $this->defaultLanguage = $language;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function status(): TenantStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function defaultLanguage(): Language
    {
        return $this->defaultLanguage;
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }
}
