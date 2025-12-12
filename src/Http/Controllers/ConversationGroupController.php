<?php

namespace Upsoftware\Conversation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Upsoftware\Conversation\Models\ConversationGroup;

class ConversationGroupController extends Controller
{
    /**
     * GET /api/conversation-groups
     * Zwraca listę wszystkich stałych grup w szkole użytkownika.
     * Używane np. do wyświetlenia listy "Kategorie" w UI.
     */
    public function index()
    {
        $user = Auth::user();

        // SECURITY: Pobieramy tylko grupy z tej samej szkoły.
        $groups = ConversationGroup::where('school_id', $user->school_id)
            ->orderBy('name')
            ->get();
            // Opcjonalnie: można dodać ->withCount('conversations') aby pokazać ile jest tematów w środku

        return response()->json($groups);
    }

    /**
     * POST /api/conversation-groups
     * Tworzy nową stałą grupę (kontener).
     * TODO: Warto dodać tu autoryzację (np. tylko dla Dyrektora/Admina).
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Przykładowa autoryzacja (jeśli używasz Policies lub ról)
        // $this->authorize('create', ConversationGroup::class);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:conversation_groups,name,NULL,id,school_id,' . $user->school_id,
            // Unikalna nazwa w obrębie danej szkoły
        ]);

        $group = ConversationGroup::create([
            'school_id' => $user->school_id,
            'name' => $validated['name'],
        ]);

        return response()->json($group, 201);
    }

    /**
     * GET /api/conversation-groups/{conversationGroup}
     * Zwraca szczegóły grupy WRAZ z listą konwersacji (tematów) wewnątrz niej.
     * KLUCZOWE: Pokazuje tylko te tematy, w których user jest uczestnikiem.
     */
    public function show(ConversationGroup $conversationGroup)
    {
        $user = Auth::user();

        // 1. SECURITY CHECK: Czy grupa należy do szkoły użytkownika?
        if ($conversationGroup->school_id !== $user->school_id) {
            abort(403, 'Access denied to this group.');
        }

        // 2. Ładowanie relacji (Eager Loading) z filtrowaniem.
        // Chcemy pobrać konwersacje należące do tej grupy, ALE TYLKO TE,
        // w których bieżący użytkownik jest uczestnikiem (jest w tabeli pivot).
        $conversationGroup->load(['conversations' => function ($query) use ($user) {
            $query->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderByDesc('last_message_at') // Najświeższe tematy na górze
            // Opcjonalnie: załadujmy od razu uczestników tych tematów (np. do awatarów)
            ->with('participants:id,name,avatar_path');
        }]);

        return response()->json($conversationGroup);
    }

    /**
     * PUT/PATCH /api/conversation-groups/{conversationGroup}
     * Zmiana nazwy grupy.
     */
    public function update(Request $request, ConversationGroup $conversationGroup)
    {
        $user = Auth::user();
        if ($conversationGroup->school_id !== $user->school_id) {
            abort(403);
        }
        // TODO: Autoryzacja (tylko admin)

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:conversation_groups,name,' . $conversationGroup->id . ',id,school_id,' . $user->school_id,
        ]);

        $conversationGroup->update($validated);

        return response()->json($conversationGroup);
    }

    /**
     * DELETE /api/conversation-groups/{conversationGroup}
     * Archiwizacja grupy (Soft Delete).
     */
    public function destroy(ConversationGroup $conversationGroup)
    {
        $user = Auth::user();
        if ($conversationGroup->school_id !== $user->school_id) {
            abort(403);
        }
        // TODO: Autoryzacja (tylko admin)

        // Konwersacje w środku zostaną, ale będą "odpięte" (conversation_group_id = null)
        // dzięki ustawieniu onDelete('set null') w migracji.

        $conversationGroup->delete();

        return response()->noContent(); // 204 No Content
    }
}