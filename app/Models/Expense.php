<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'payment_method_id',
        'amount',
        'currency',
        'payment_date',
        'description',
        'amount_usd',
        'exchange_rate_used',
        'manually_converted',
        'payment_reference',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'exchange_rate_used' => 'decimal:6',
        'manually_converted' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}