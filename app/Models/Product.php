<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Importar SoftDeletes

class Product extends Model
{
    use HasFactory, SoftDeletes; // Usar el Trait

    protected $guarded = [];

    protected $casts = [
        'required_metadata' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}