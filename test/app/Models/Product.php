<?php

namespace App\Models;

use app\Enums\Currency;
use Carbon\Carbon;
use Decimal\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $name
 * @property string $description
 * @property integer $stock
 * @property Decimal $cost
 * @property Currency $currency
 * @property boolean $is_discontinued
 * @property Carbon $discontinued_date
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'stock',
        'cost',
        'currency',
        'is_discontinued',
        'discontinued_date',
    ];

    protected $casts = [
        'is_discontinued' => 'boolean',
        'discontinued_date' => 'datetime',
        'cost' => 'decimal:2',
        'currency' => Currency::class
    ];
}
