<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Pemetaan alias role (dipakai di route) ke nilai kolom `users.role` di database.
     * Enum DB: administrator, guru, siswa.
     *
     * @var array<string, array<int, string>>
     */
    protected const ROLE_ALIASES = [
        'admin'      => ['administrator'],
        'bk'         => ['guru'],
        'wali_kelas' => ['guru'],
    ];

    /**
     * Pastikan user terautentikasi memiliki salah satu role yang diizinkan.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            ], 401);
        }

        if (! in_array($user->role, $this->resolveRoles($roles), true)) {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => "Akses ditolak. Peran Anda ({$user->role}) tidak memiliki izin untuk mengakses resource ini.",
            ], 403);
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    protected function resolveRoles(array $roles): array
    {
        $resolved = [];

        foreach ($roles as $role) {
            $role = trim($role);
            $resolved = array_merge($resolved, self::ROLE_ALIASES[$role] ?? [$role]);
        }

        return array_values(array_unique($resolved));
    }
}
