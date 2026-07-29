<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    protected $fillable = [
        'user_id',
        'employee_number',
        'specialty',
        'identity_document_type',
        'identity_document_number',
        'identity_document_issued_at',
        'identity_document_expires_at',
        'address',
        'emergency_contact',
        'default_hourly_rate',
        'withholding_tax_rate',
        'payment_method',
        'payment_reference',
        'contract_type',
        'hired_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'identity_document_issued_at' => 'date',
            'identity_document_expires_at' => 'date',
            'default_hourly_rate' => 'decimal:2',
            'withholding_tax_rate' => 'decimal:2',
            'hired_at' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
