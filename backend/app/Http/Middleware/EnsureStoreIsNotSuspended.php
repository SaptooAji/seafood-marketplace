<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\StoreStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a suspended store out of the seller API (AC-7), as the /seller panel
 * does through User::canAccessTenant(). Checked on every request, so a
 * suspension takes effect on tokens already issued and a reactivation needs
 * no new sign-in. A user without a store passes; the controller answers 404.
 */
class EnsureStoreIsNotSuspended
{
    /**
     * Refuse the request with a 403 when the seller's store is suspended.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->currentStore()?->status === StoreStatus::Suspended) {
            abort(Response::HTTP_FORBIDDEN, 'Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali.');
        }

        return $next($request);
    }
}
