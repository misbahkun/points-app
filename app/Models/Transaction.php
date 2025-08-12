<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'description',
        'transacted_at'
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'amount' => 'integer'
    ];

    public function point()
    {
        return $this->hasOne(Point::class);
    }
}