<?php

namespace Upsoftware\Conversation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Upsoftware\Conversation\Models\Conversation;

class ConversationMessageController extends Controller
{
    /**
     * POST /api/conversations/{conversation}/messages
     * Wysyła nową wiadomość do istniejącej konwersacji.
     */
    public function store(Request $request, Conversation $conversation)
    {
        $user = Auth::user();

        // Powtórka security checków (czy szkoła się zgadza i czy jest uczestnikiem)
        if ($conversation->school_id !== $user->school_id ||
            !$conversation->participants()->where('users.id', $user->id)->exists()) {
            abort(403);
        }

        // Walidacja danych wejściowych
        $validated = $request->validate([
            'body' => 'required_without:attachment|string|max:5000', // Wymagane jeśli nie ma załącznika
            'topic_subject' => 'nullable|string|max:255', // Opcjonalny temat (cecha hybrydowa)
            'scheduled_for' => 'nullable|date|after:now', // Opcjonalne planowanie (cecha hybrydowa)
            // 'attachment' => 'nullable|file|max:10240' // Obsługa plików później
        ]);

        // Tworzenie wiadomości przez relację
        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $validated['body'] ?? null,
            'topic_subject' => $validated['topic_subject'] ?? null,
            'scheduled_for' => $validated['scheduled_for'] ?? null,
        ]);

        // TODO: Jeśli wiadomość NIE jest zaplanowana na przyszłość (scheduled_for is null):
        // 1. Zaktualizuj pole 'last_message_at' w modelu Conversation na now().
        // 2. Wyślij event na Websocket (np. przez Laravel Reverb/Pusher), żeby inni zobaczyli wiadomość na żywo.

        return response()->json($message->load('sender:id,name,avatar_path'), 201);
    }
}