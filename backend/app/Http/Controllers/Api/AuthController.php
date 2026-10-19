<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmployeeMasterOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || $user->status !== 'active') {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        $user->tokens()->where('name', 'technician-app')->delete();
        $token = $user->createToken('technician-app')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $this->userPayload($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function updateLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', Rule::in(EmployeeMasterOptions::languageNames())],
        ]);

        $request->user()->forceFill(['preferred_language' => $validated['language']])->save();

        return response()->json(['user' => $this->userPayload($request->user()->fresh())]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $user->load(['roles', 'employeeProfile:id,user_id,languages,profile_picture_path,date_of_joining']);
        $switcher = EmployeeMasterOptions::switcherLanguagesFor($user);
        $preferred = EmployeeMasterOptions::preferredLanguageFor($user);

        return $user->toArray() + [
            'language' => [
                'preferred' => $preferred,
                'preferred_code' => EmployeeMasterOptions::languageCode($preferred),
                'known' => collect($switcher)->map(fn (string $code, string $name): array => ['name' => $name, 'code' => $code])->values()->all(),
            ],
        ];
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
