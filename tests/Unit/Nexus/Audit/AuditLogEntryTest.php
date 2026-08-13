<?php

namespace Tests\Unit\Nexus\Audit;

use App\Domains\Nexus\Audit\Domain\Entities\AuditLogEntry;
use App\Domains\Nexus\Audit\Domain\ValueObjects\AuditOutcome;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AuditLogEntryTest extends TestCase
{
    public function test_genesisHash_isExactly64Characters(): void
    {
        // Regression guard: every entryHash/prevHash column is
        // string(64) (a sha256 hex digest's length) — a genesis marker of
        // any other length would either get silently truncated by the DB
        // or make the very first row's prevHash never equal the constant
        // read back from a fresh instance.
        $this->assertSame(64, strlen(AuditLogEntry::GENESIS_HASH));
        $this->assertSame(str_repeat('0', 64), AuditLogEntry::GENESIS_HASH);
    }

    public function test_record_capturesAllFieldsAndComputesA64CharacterHash(): void
    {
        $entry = AuditLogEntry::record(
            sequence: 1,
            prevHash: AuditLogEntry::GENESIS_HASH,
            capabilityName: 'nexus.credit.balance',
            businessId: 5,
            coreAgentId: 9,
            status: AuditOutcome::Success,
            inputSummary: ['foo', 'bar'],
            executionTimeMs: 42,
        );

        $this->assertNull($entry->id());
        $this->assertSame(1, $entry->sequence());
        $this->assertSame(AuditLogEntry::GENESIS_HASH, $entry->prevHash());
        $this->assertSame('nexus.credit.balance', $entry->capabilityName());
        $this->assertSame(5, $entry->businessId());
        $this->assertSame(9, $entry->coreAgentId());
        $this->assertSame(AuditOutcome::Success, $entry->status());
        $this->assertSame(['foo', 'bar'], $entry->inputSummary());
        $this->assertSame(42, $entry->executionTimeMs());
        $this->assertSame(64, strlen($entry->entryHash()));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $entry->entryHash());
    }

    public function test_record_withNullBusinessAndAgent_isAllowed(): void
    {
        // Denied/error entries logged before ResolveActingBusinessAction
        // could identify a Business (or an admin ping with no Agent at
        // all) — same nullable shape LLMUsageLog already established.
        $entry = AuditLogEntry::record(
            sequence: 1,
            prevHash: AuditLogEntry::GENESIS_HASH,
            capabilityName: 'nexus.negotiation.propose',
            businessId: null,
            coreAgentId: null,
            status: AuditOutcome::Error,
            inputSummary: [],
            executionTimeMs: 3,
        );

        $this->assertNull($entry->businessId());
        $this->assertNull($entry->coreAgentId());
    }

    public function test_computeHash_isDeterministicForIdenticalInput(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-19T10:00:00+00:00');

        $first = AuditLogEntry::computeHash(1, AuditLogEntry::GENESIS_HASH, 'nexus.x', 1, 2, AuditOutcome::Success, ['a'], 10, $createdAt);
        $second = AuditLogEntry::computeHash(1, AuditLogEntry::GENESIS_HASH, 'nexus.x', 1, 2, AuditOutcome::Success, ['a'], 10, $createdAt);

        $this->assertSame($first, $second);
    }

    public function test_computeHash_changesWhenAnyFieldChanges(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-19T10:00:00+00:00');
        $baseline = AuditLogEntry::computeHash(1, AuditLogEntry::GENESIS_HASH, 'nexus.x', 1, 2, AuditOutcome::Success, ['a'], 10, $createdAt);

        // Tampering with just the outcome (e.g. rewriting a 'denied' row
        // to look like 'success' after the fact) must change the hash —
        // this is the entire premise VerifyAuditChainIntegrityAction
        // relies on to detect tampering.
        $tamperedStatus = AuditLogEntry::computeHash(1, AuditLogEntry::GENESIS_HASH, 'nexus.x', 1, 2, AuditOutcome::Denied, ['a'], 10, $createdAt);
        $this->assertNotSame($baseline, $tamperedStatus);

        $tamperedBusiness = AuditLogEntry::computeHash(1, AuditLogEntry::GENESIS_HASH, 'nexus.x', 999, 2, AuditOutcome::Success, ['a'], 10, $createdAt);
        $this->assertNotSame($baseline, $tamperedBusiness);

        $tamperedPrev = AuditLogEntry::computeHash(1, str_repeat('f', 64), 'nexus.x', 1, 2, AuditOutcome::Success, ['a'], 10, $createdAt);
        $this->assertNotSame($baseline, $tamperedPrev);
    }

    public function test_chain_secondEntrysPrevHash_mustEqualFirstEntrysEntryHash(): void
    {
        $first = AuditLogEntry::record(
            sequence: 1,
            prevHash: AuditLogEntry::GENESIS_HASH,
            capabilityName: 'nexus.credit.balance',
            businessId: 1,
            coreAgentId: 1,
            status: AuditOutcome::Success,
            inputSummary: [],
            executionTimeMs: 5,
        );

        $second = AuditLogEntry::record(
            sequence: 2,
            prevHash: $first->entryHash(),
            capabilityName: 'nexus.marketplace.search',
            businessId: 1,
            coreAgentId: 1,
            status: AuditOutcome::Success,
            inputSummary: [],
            executionTimeMs: 8,
        );

        $this->assertSame($first->entryHash(), $second->prevHash());
        $this->assertNotSame($first->entryHash(), $second->entryHash());
    }

    public function test_fromPersisted_trustsTheGivenHashRatherThanRecomputing(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-19T10:00:00+00:00');

        $entry = AuditLogEntry::fromPersisted(
            id: 7,
            sequence: 3,
            prevHash: AuditLogEntry::GENESIS_HASH,
            entryHash: 'not-a-real-hash-but-fromPersisted-must-not-care',
            capabilityName: 'nexus.x',
            businessId: 1,
            coreAgentId: 2,
            status: AuditOutcome::Success,
            inputSummary: [],
            executionTimeMs: 1,
            createdAt: $createdAt,
        );

        $this->assertSame(7, $entry->id());
        $this->assertSame('not-a-real-hash-but-fromPersisted-must-not-care', $entry->entryHash());
    }
}
