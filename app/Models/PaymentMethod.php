<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Verificar si este método de pago es "Créditos Servidor"
     * Se usa para determinar si la venta debe debitar balance del proveedor
     */
    public function isServerCredits(): bool
    {
        // Verificar por nombre (case insensitive)
        $serverCreditNames = [
            'créditos servidor',
            'creditos servidor',
            'server credits',
            'crédito servidor',
            'credito servidor',
        ];

        return in_array(strtolower(trim($this->name)), $serverCreditNames);
    }
}