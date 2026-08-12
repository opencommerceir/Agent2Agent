<?php

namespace App\Domains\Nexus\Admin\Domain\Entities;

/**
 * A single admin-editable key/value override — the hot-reload mechanism
 * Phase 3/M5's Admin Margin Settings needs and nothing in this codebase
 * had before (no DB-backed settings table, no static config() override,
 * existed anywhere prior to this). Framework-free (Domain Layer Rules).
 * Value stays a plain string here; MarginSettingsService owns parsing it
 * back to a float — this entity has no opinion on what a given key means.
 */
final class PlatformSetting
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $key,
        private string $value,
    ) {
    }

    public static function set(string $key, string $value): self
    {
        return new self(id: null, key: $key, value: $value);
    }

    public function update(string $value): void
    {
        $this->value = $value;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): string
    {
        return $this->value;
    }
}
