<?php

namespace Module\Upload\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadDiagnostic extends Model
{
    protected $table = 'upload_diagnostics';

    protected $fillable = ['user_id', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
