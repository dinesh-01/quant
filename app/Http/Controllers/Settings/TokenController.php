<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TokenStoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    /**
     * Tokens are minted here because a CI job cannot log in. The plaintext
     * value is shown once on the next render and then discarded — it is never
     * stored, and Sanctum only keeps a hash.
     */
    public function index(Request $request): Response
    {
        $tokens = $this->actingUser($request)
            ->tokens()
            ->orderByDesc('id')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('settings/tokens', [
            'tokens' => $tokens,
            'plainTextToken' => $request->session()->pull('plainTextToken'),
        ]);
    }

    public function store(TokenStoreRequest $request): RedirectResponse
    {
        $newAccessToken = $this->actingUser($request)->createToken($request->tokenName());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Token created. Copy it now — it will not be shown again.'),
        ]);

        return back()->with('plainTextToken', $newAccessToken->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $this->actingUser($request)
            ->tokens()
            ->whereKey($token)
            ->firstOrFail()
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }
}
