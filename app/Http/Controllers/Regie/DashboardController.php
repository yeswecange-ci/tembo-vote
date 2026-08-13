<?php

namespace App\Http\Controllers\Regie;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GuestSession;
use App\Models\Photo;
use App\Models\Vote;
use App\Services\AccessTokenService;
use App\Support\EventPhase;
use Illuminate\View\View;

/**
 * Vue d'ensemble de la soirée : les chiffres qui comptent, le QR d'accès,
 * la phase, et les dernières actions de la régie.
 */
class DashboardController extends Controller
{
    public function show(AccessTokenService $accessTokenService): View
    {
        $accessToken = $accessTokenService->current();

        $countsByStatus = Photo::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('regie.dashboard', [
            'pendingCount' => (int) $countsByStatus->get('pending', 0),
            'approvedCount' => (int) $countsByStatus->get('approved', 0),
            'rejectedCount' => (int) $countsByStatus->get('rejected', 0),
            'votesCount' => Vote::query()->count(),
            'sessionsCount' => GuestSession::query()->count(),
            'phaseCourante' => EventPhase::current(),
            'accessToken' => $accessToken,
            'qr' => $accessTokenService->qrDataUri($accessToken),
            'dernieresActions' => AuditLog::query()->latest('id')->limit(6)->get(),
        ]);
    }
}
