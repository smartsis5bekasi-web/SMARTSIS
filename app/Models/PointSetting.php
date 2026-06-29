<?php

namespace App\Models;

use Database\Factories\PointSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $initial_point
 * @property int $target_point
 * @property int $min_point
 */
class PointSetting extends Model
{
    /** @use HasFactory<PointSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'initial_point',
        'target_point',
        'min_point',
    ];

    /**
     * The singleton settings row, created with defaults on first access.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'initial_point' => 100,
            'target_point' => 100,
            'min_point' => 40,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_point' => 'integer',
            'target_point' => 'integer',
            'min_point' => 'integer',
        ];
    }
}
