<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan reset password (fitur terpisah dari laporan/pengaduan).
 *
 * Alur: user lupa password membuat request (tanpa login) -> Admin memverifikasi
 * -> Admin menjalankan reset password via logic yang SUDAH ADA
 * (AdminUserController::resetPassword) -> request ditandai completed.
 *
 * Tidak ada password yang disimpan di tabel ini — plaintext maupun hash.
 */
class PasswordResetRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
        self::STATUS_COMPLETED,
    ];

    protected $table = 'password_reset_requests';

    protected $fillable = [
        'identifier_type',
        'identifier',
        'requested_account_id',
        'requested_account_type',
        'message',
        'status',
        'admin_note',
        'handled_by',
        'handled_at',
        'completed_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
