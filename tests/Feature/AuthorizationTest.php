<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireRole;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_guest_cannot_access_admin_reports(): void
    {
        $response = $this->getJson('/api/admin/laporan');

        $response->assertUnauthorized();
    }

    public function test_warga_role_is_forbidden_from_admin_reports(): void
    {
        $request = Request::create('/api/admin/laporan', 'GET');
        $request->setUserResolver(fn () => (object) ['role' => 'warga']);

        $response = (new RequireRole)->handle(
            $request,
            fn () => response()->json(['success' => true]),
            'admin',
            'petugas'
        );

        $this->assertSame(403, $response->getStatusCode());
    }
}
