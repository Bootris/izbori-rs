<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ProtocolRevision;
use PHPUnit\Framework\TestCase;

class ProtocolRevisionTest extends TestCase
{
    public function test_action_labels_are_serbian(): void
    {
        $this->assertSame('Unet', ProtocolRevision::formatActionLabel('created'));
        $this->assertSame('Izmenjen', ProtocolRevision::formatActionLabel('updated'));
        $this->assertSame('Verifikovan', ProtocolRevision::formatActionLabel('verified'));
        $this->assertSame('Vraćen na ispravku', ProtocolRevision::formatActionLabel('unverified'));
        $this->assertSame('Poništen', ProtocolRevision::formatActionLabel('annulled'));
    }

    public function test_created_revision_omits_null_arrows_and_zero_lists(): void
    {
        $lines = ProtocolRevision::formatChangeLines([
            'registered_voters' => [null, 851],
            'ballots_received' => [null, 871],
            'ballots_unused' => [null, 377],
            'voters_voted' => [null, 494],
            'ballots_in_box' => [null, 494],
            'ballots_valid' => [null, 486],
            'ballots_invalid' => [null, 8],
            'items' => [null, ['1' => 210, '2' => 112, '3' => 0]],
        ], [
            1 => '1. Lista A',
            2 => '2. Lista B',
            3 => '3. Lista C',
        ]);

        $this->assertSame([
            'Upisanih birača: 851',
            'Primljeno listića: 871',
            'Neupotrebljeno: 377',
            'Glasalo: 494',
            'U kutiji: 494',
            'Važećih: 486',
            'Nevažećih: 8',
            '1. Lista A: 210',
            '2. Lista B: 112',
        ], $lines);
        $this->assertNotContains('3. Lista C: 0', $lines);
        $this->assertStringNotContainsString('null', implode("\n", $lines));
        $this->assertStringNotContainsString('[', implode("\n", $lines));
    }

    public function test_updated_revision_shows_only_changed_fields_with_arrows(): void
    {
        $lines = ProtocolRevision::formatChangeLines([
            'ballots_valid' => [590, 589],
            'ballots_invalid' => [10, 11],
            'items' => [
                [1 => 300, 2 => 290],
                [1 => 299, 2 => 290],
            ],
        ], [
            1 => '1. Lista A',
            2 => '2. Lista B',
        ]);

        $this->assertSame([
            'Važećih: 590 → 589',
            'Nevažećih: 10 → 11',
            '1. Lista A: 300 → 299',
        ], $lines);
    }

    public function test_reason_and_empty_changes(): void
    {
        $this->assertSame(
            ['Razlog: Pogrešno prepisan broj nevažećih.'],
            ProtocolRevision::formatChangeLines(['reason' => [null, 'Pogrešno prepisan broj nevažećih.']]),
        );
        $this->assertSame([], ProtocolRevision::formatChangeLines(null));
        $this->assertSame([], ProtocolRevision::formatChangeLines([]));
    }
}
