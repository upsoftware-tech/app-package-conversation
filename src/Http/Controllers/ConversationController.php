<?php

namespace Upsoftware\Conversation\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Upsoftware\Core\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Upsoftware\Conversation\Models\Conversation;
use Upsoftware\Conversation\Models\ConversationMessage;

class ConversationController extends Controller
{
    /**
     * GET /api/conversations
     * Zwraca listę rozmów dla zalogowanego użytkownika (Lewa Kolumna).
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Pobieramy konwersacje, w których użytkownik uczestniczy.
        // Musimy je posortować po dacie ostatniej wiadomości malejąco.
        $conversations = $user->conversations()
            //->where('school_id', $user->school_id) // Safety check: tylko z jego szkoły
            ->with(['latestMessage', 'participants' => function($query) use ($user) {
                 // Opcjonalnie: ograniczamy listę uczestników, żeby nie pobierać 30 osób z klasy
                 // jeśli potrzebujemy tylko pokazać awatary 2-3 osób.
                 $query->where('users.id', '!=', $user->id)->limit(3);
            }])
            // Kluczowe: Sortowanie po kolumnie z tabeli conversations
            ->orderByDesc('last_message_at')
            ->paginate(20);

        // TODO: W Resource/Transformerze trzeba będzie obliczyć liczbę nieprzeczytanych wiadomości
        // porównując $conversation->last_message_at z $conversation->pivot->last_read_at

        return response()->json($conversations);
    }

    /**
     * GET /api/conversations/{conversation}
     * Zwraca szczegóły jednej konwersacji i jej historię (Prawa Kolumna po kliknięciu).
     */
    public function show(Conversation $conversation)
    {
        $user = Auth::user();

        // SECURITY CHECK 1: Czy konwersacja należy do tej samej szkoły co user?
        if ($conversation->school_id !== $user->school_id) {
            abort(403, 'Unauthorized access to school data.');
        }

        // SECURITY CHECK 2: Czy użytkownik jest uczestnikiem tej rozmowy?
        // Używamy metody exists() na relacji participants().
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            abort(403, 'You are not a participant of this conversation.');
        }

        // Pobieranie wiadomości (historia czatu)
        $messages = $conversation->messages()
            ->visible() // Używamy naszego scope'a (bez zaplanowanych na przyszłość)
            ->with('sender:id,name,avatar_path') // Eager loading nadawcy (tylko potrzebne pola)
            ->orderBy('created_at', 'asc') // Najstarsze na górze, najnowsze na dole
            ->paginate(50); // Paginacja "w górę" (infinite scroll)

        // TODO: WAŻNE! W tym momencie należałoby zaktualizować 'last_read_at' w tabeli pivot
        // dla tego użytkownika na now(). To oznacza "przeczytanie" czatu.
        // $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json([
            'conversation' => $conversation->load('participants:id,name,avatar_path'), // Załaduj pełną listę uczestników
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $user = User::find(1); //$request->user();

        // 1. WALIDACJA DANYCH WEJŚCIOWYCH
        // Frontend musi przysłać tablicę ID uczestników i obiekt wiadomości.
        $validated = $request->validate([
            // Tablica ID użytkowników, do których piszemy (bez nas samych)
            'participants' => 'required|array|min:1',
            'participants.*' => 'integer|distinct|not_in:' . $user->id,

            // Opcjonalna nazwa (ma sens tylko dla grup)
            'name' => 'nullable|string|max:100',

            // Struktura pierwszej wiadomości
            'message.body' => 'required|string|max:5000',
            'message.topic_subject' => 'nullable|string|max:255', // Hybrydowy temat
            'message.scheduled_for' => 'nullable|date|after:now', // Planowanie wysyłki
        ]);

        $participantIds = $validated['participants'];

        // 2. SECURITY CHECK (Kluczowe dla multi-tenancy!)
        // Sprawdzamy, czy wszyscy podani uczestnicy należą do TEJ SAMEJ szkoły co nadawca.
        // Nie możemy ufać samym ID przesłanym z frontu.
        //$validParticipantsCount = User::whereIn('id', $participantIds)
            //->where('school_id', $user->school_id)
            //->count();

        //if ($validParticipantsCount !== count($participantIds)) {
            // Jeśli liczby się nie zgadzają, ktoś próbuje dodać usera z innej szkoły
            // lub nieistniejącego ID.
            //abort(403, 'Attempt to add invalid participants or participants from another school.');
        //}

        // 3. OKREŚLENIE TYPU KONWERSACJI
        // Jeśli 1 uczestnik (+ my) -> direct. Więcej -> group.
        $type = count($participantIds) === 1 ? 'direct' : 'group';
        // Nazwę ustawiamy tylko dla grup
        $name = ($type === 'group') ? ($validated['name'] ?? null) : null;


        // 4. ROZPOCZĘCIE TRANSAKCJI BAZODANOWEJ
        // Wszystko poniżej musi się udać, albo nic się nie zapisze.
        try {
            $conversation = DB::transaction(function () use ($user, $validated, $participantIds, $type, $name) {

                // A) Utwórz Konwersację
                $conversation = Conversation::create([
                    //'school_id' => $user->school_id,
                    'creator_id' => $user->id,
                    'type' => $type,
                    'name' => $name,
                    // Na razie ustawiamy stary czas, zaktualizujemy go po dodaniu wiadomości
                    // jeśli wiadomość NIE jest zaplanowana.
                    'last_message_at' => now()->subYear(),
                ]);

                // B) Dodaj Uczestników (Tabela Pivot)
                // Przygotowujemy tablicę do metody sync() lub attach()
                $participantsData = [];

                // Dodajemy nas samych (twórcę) - opcjonalnie jako admina grupy
                $participantsData[$user->id] = ['is_admin' => true, 'joined_at' => now()];

                // Dodajemy resztę uczestników
                foreach ($participantIds as $id) {
                    $participantsData[$id] = ['is_admin' => false, 'joined_at' => now()];
                }

                // Zapisujemy do pivota
                $conversation->participants()->attach($participantsData);


                // C) Utwórz Pierwszą Wiadomość
                $messageData = $validated['message'];
                $message = new ConversationMessage([
                    'conversation_id' => $conversation->id, // Można pominąć przy użyciu relacji, ale dla jasności
                    'user_id' => $user->id,
                    'body' => $messageData['body'],
                    'topic_subject' => $messageData['topic_subject'] ?? null,
                    'scheduled_for' => $messageData['scheduled_for'] ?? null,
                ]);
                // Zapisz przez relację
                $conversation->messages()->save($message);


                // D) Aktualizacja Metadanych Konwersacji (Jeśli wiadomość jest "na teraz")
                if (is_null($message->scheduled_for)) {
                    $conversation->update(['last_message_at' => $message->created_at]);

                    // TODO: W tym miejscu wywołalibyśmy Event dla Websocketa (np. NewConversationCreated)
                    // aby poinformować uczestników na żywo.
                }

                return $conversation;
            }); // Koniec transakcji

            // 5. Przygotowanie odpowiedzi
            // Ładujemy dane potrzebne frontendowi do wyświetlenia nowej rozmowy w prawej kolumnie
            $conversation->load(['participants:id,name,avatar_path', 'latestMessage']);

            return response()->json($conversation, 201); // 201 Created

        } catch (\Exception $e) {
            // Jeśli coś poszło nie tak w transakcji, Laravel zrobi rollback automatycznie.
            // Logujemy błąd i zwracamy info.
            // Log::error('Error creating conversation: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create conversation.', 'error' => $e->getMessage()], 500);
        }
    }
}