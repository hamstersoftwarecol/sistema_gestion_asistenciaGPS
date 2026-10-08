<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'title', 'category', 'file_path', 'original_name', 'expires_on', 'uploaded_by'])]
class EmployeeDocument extends Model
{
    public const CATEGORIES = [
        'contract' => 'Contrato',
        'id' => 'Documento de identidad',
        'certificate' => 'Certificado / curso',
        'medical' => 'Examen médico',
        'license' => 'Licencia / permiso de trabajo',
        'other' => 'Otro',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => DateOnly::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpiringSoon(): bool
    {
        return $this->expires_on !== null
            && $this->expires_on->lte(now()->addDays((int) setting('document_expiry_alert_days')));
    }
}
