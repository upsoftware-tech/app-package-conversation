<?php

namespace Upsoftware\Conversation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationGroup extends Model
{
    use HasFactory, SoftDeletes;

    // Pola, które można wypełniać masowo (create/update)
    protected $fillable = [
        'school_id',
        'name',
    ];

    // --- RELACJE ---

    /**
     * Grupa należy do konkretnej szkoły.
     * Kluczowe dla bezpieczeństwa (multi-tenancy).
     */
    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    /**
     * Grupa zawiera wiele konwersacji (tematów).
     * Np. Grupa "Angliści" ma konwersacje "Podręczniki" i "Wyjazd".
     */
    public function conversations(): HasMany
    {
        // Sortujemy domyślnie od tych z najnowszą aktywnością
        return $this->hasMany(Conversation::class)->orderByDesc('last_message_at');
    }
}