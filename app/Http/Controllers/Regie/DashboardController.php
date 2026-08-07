<?php

namespace App\Http\Controllers\Regie;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GuestSession;
use App\Models\Photo;
use App\Models\Vote;
use App\Services\PinService;
use App\Support\EventPhase;
use Illuminate\View\View;

/**
 * Vue d'ensemble de la soirée : les chiffres qui comptent, le PIN,
 * la phase, et les dernières actions de la régie.
 */
class DashboardController extends Controller
{
    public function show(PinService $pinService): View
    {
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
            'pin' => $pinService->current(),
            'dernieresActions' => AuditLog::query()->latest('id')->limit(6)->get(),
        ]);
    }
}
