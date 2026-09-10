<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function productSnapshot(): BelongsTo
    {
        return $this->belongsTo(ProductSnapshot::class);
    }
}
