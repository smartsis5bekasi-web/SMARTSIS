<?php

namespace App\Models;

use App\Enums\WarningLevel;
use Database\Factories\WarningSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $sp1_threshold
 * @property int $sp2_threshold
 * @property int $sp3_threshold
 */
class WarningSetting extends Model
{
    /** @use HasFactory<WarningSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'sp1_threshold',
        'sp2_threshold',
        'sp3_threshold',
    ];

    /**
     * The singleton settings row, created with the PRD defaults on first
     * access (F-20 — SP1 ≤80, SP2 ≤60, SP3 ≤40).
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'sp1_threshold' => 80,
            'sp2_threshold' => 60,
            'sp3_threshold' => 40,
        ]);
    }

    /**
     * The point threshold that triggers the given level.
     */
    public function thresholdFor(WarningLevel $level): int
    {
        return match ($level) {
            WarningLevel::Sp1 => $this->sp1_threshold,
            WarningLevel::Sp2 => $this->sp2_threshold,
            WarningLevel::Sp3 => $this->sp3_threshold,
        };
    }

    /**
     * The most severe level the given balance qualifies for, or null when the
     * balance is still above every threshold.
     */
    public function levelFor(int $points): ?WarningLevel
    {
        return match (true) {
            $points <= $this->sp3_threshold => WarningLevel::Sp3,
            $points <= $this->sp2_threshold => WarningLevel::Sp2,
            $points <= $this->sp1_threshold => WarningLevel::Sp1,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sp1_threshold' => 'integer',
            'sp2_threshold' => 'integer',
            'sp3_threshold' => 'integer',
        ];
    }
}
