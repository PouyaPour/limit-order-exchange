<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\SymbolEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\Profile\ProfileResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'balance' => '10000.00000000',
        ]);

        $user->assets()->create([
            'symbol' => SymbolEnum::BTC->value,
            'amount' => '10000.00000000',
        ]);

        $token = $this->authService->generateToken(
            user: $user,
            userAgent: $request->userAgent(),
            ipAddress: $request->ip(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => ProfileResource::make($user),
                'token' => $token->plainTextToken,
                'expired_at' => $token->accessToken->expires_at?->format('Y-m-d H:i:s'),
                'abilities' => $this->authService->determineAbilities($user),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        abort_if(! $user, Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.login_failed'));

        abort_if(! Hash::check($request->validated('password'), $user->password), Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.login_failed'));

        $token = $this->authService->generateToken($user, $request->userAgent(), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => ProfileResource::make($user),
                'token' => $token->plainTextToken,
                'expired_at' => $token->accessToken->expires_at?->format('Y-m-d H:i:s'),
                'abilities' => $this->authService->determineAbilities($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }
}
