<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\AkunWarga;
use App\Models\Pelapor;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('akun_warga', 'email'),
            ],
            'kontak' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('akun_warga', 'no_hp'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'device_token' => ['nullable', 'string', 'max:512'],
        ]);

        if (\App\Models\User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email sudah digunakan.',
            ], 422);
        }

        $akunWarga = DB::transaction(function () use ($validated) {
            $akunWarga = AkunWarga::create([
                'nama' => $validated['nama'],
                'email' => $validated['email'],
                'no_hp' => $validated['kontak'] ?? null,
                'password' => Hash::make($validated['password']),
            ]);

            // Akun warga selalu memiliki identity sendiri. Laporan anonymous
            // tidak pernah di-claim otomatis ketika register.
            $akunWarga->pelapor()->create([
                'nama' => $akunWarga->nama,
                'kontak' => $akunWarga->no_hp,
                'is_anonim' => false,
            ]);

            return $akunWarga;
        });

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Silakan login.',
            'data' => [
                'id' => $akunWarga->id,
                'nama' => $akunWarga->nama,
                'email' => $akunWarga->email,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'identifier' => [
                'nullable',
                'string',
                'max:255',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'device_token' => ['nullable', 'string', 'max:512'],
        ]);

        $identifier = $credentials['identifier']
            ?? $credentials['email']
            ?? null;

        if ($identifier === null) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau username wajib diisi.',
            ], 422);
        }

        $user = \App\Models\User::where('email', $identifier)->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            if (!in_array($user->role, ['admin', 'petugas'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email atau password salah.',
                ], 401);
            }

            $token = Auth::guard('api')->login($user);
            $user->load('unitLayanan:id,nama,kode');
            $units = $user->unitLayanan->map(fn ($unit) => [
                'id' => $unit->id,
                'kode' => $unit->kode,
                'nama' => $unit->nama,
            ])->values()->all();

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'unit_layanan' => $units,
                    ],
                ],
            ]);
        }

        $akunWarga = AkunWarga::where('email', $identifier)->first();

        if (!$akunWarga || !Hash::check($credentials['password'], $akunWarga->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $pelapor = DB::transaction(function () use ($akunWarga) {
            $pelapor = $akunWarga->pelapor()->latest('id')->first();

            return $pelapor ?: Pelapor::create([
                'nama' => $akunWarga->nama,
                'kontak' => $akunWarga->no_hp,
                'is_anonim' => false,
                'user_account_id' => $akunWarga->id,
            ]);
        });
        $token = JWTAuth::customClaims([
            'account_type' => 'warga',
            'role' => 'warga',
            'pelapor_id' => $pelapor?->id,
        ])->fromUser($akunWarga);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $akunWarga->id,
                    'name' => $akunWarga->nama,
                    'email' => $akunWarga->email,
                    'role' => 'warga',
                    'pelapor_id' => $pelapor?->id,
                ],
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if ($user instanceof \App\Models\User) {
            $user->load('unitLayanan:id,nama,kode');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user instanceof AkunWarga
                    ? $user->nama
                    : $user->name,
                'email' => $user->email,
                'role' => $user instanceof AkunWarga
                    ? 'warga'
                    : $user->role,
                'pelapor_id' => $user instanceof AkunWarga
                    ? $user->pelapor()->latest('id')->value('id')
                    : null,
                'unit_layanan' => $user instanceof \App\Models\User
                    ? $user->unitLayanan->map(fn ($unit) => [
                        'id' => $unit->id,
                        'kode' => $unit->kode,
                        'nama' => $unit->nama,
                    ])->values()
                    : [],
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }
}
