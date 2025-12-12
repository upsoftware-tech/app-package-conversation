<?php

namespace Upsoftware\Conversation\Models;

use Upsoftware\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationMessage extends Model
{
    use SoftDeletes;

    protected $table = 'conversation_messages';

    protected $fillable = [
        'conversation_id',
        'user_id', // Nadawca
        'body',
        'topic_subject', // Cecha hybrydowa (temat wiadomości)
        'scheduled_for', // Cecha hybrydowa (planowanie wysyłki)
        'type', // 'text' lub 'system'
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeVisible($query) {
        return $query->where(function ($q) {
            $q->whereNull('scheduled_for')
              ->orWhere('scheduled_for', '<=', now());
        });
    }
}