<?php

namespace App\Models;

use App\Enums\PointSource;
use App\Enums\PointType;
use Database\Factories\PointRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property PointType $type
 * @property PointSource $source
 * @property int $point
 * @property bool $is_active
 */
class PointRule extends Model
{
    /** @use HasFactory<PointRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'source',
        'point',
        'is_active',
    ];

    /**
     * The signed point delta this rule applies (positive for additions,
     * negative for deductions).
     */
    public function signedPoint(): int
    {
        return $this->type->sign() * $this->point;
    }

    /**
     * @return HasMany<PointLog, $this>
     */
    public function pointLogs(): HasMany
    {
        return $this->hasMany(PointLog::class);
    }

    /**
     * @param  Builder<PointRule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<PointRule>  $query
     */
    public function scopeAdditions(Builder $query): void
    {
        $query->where('type', PointType::Addition);
    }

    /**
     * @param  Builder<PointRule>  $query
     */
    public function scopeDeductions(Builder $query): void
    {
        $query->where('type', PointType::Deduction);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PointType::class,
            'source' => PointSource::class,
            'point' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
