<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurrentAffairItem;
use Illuminate\Http\Request;

class CurrentAffairsController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string(
            'status',
            'pending'
        )->toString();

        $allowed = [
            'pending',
            'approved',
            'rejected',
            'processed',
            'expired',
            'all',
        ];

        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $query = CurrentAffairItem::with('source')
            ->latest('published_at')
            ->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $items = $query->paginate(30)
            ->withQueryString();

        $counts = [
            'pending' =>
                CurrentAffairItem::where(
                    'status',
                    'pending'
                )->count(),

            'approved' =>
                CurrentAffairItem::where(
                    'status',
                    'approved'
                )->count(),

            'processed' =>
                CurrentAffairItem::where(
                    'status',
                    'processed'
                )->count(),

            'rejected' =>
                CurrentAffairItem::where(
                    'status',
                    'rejected'
                )->count(),
        ];

        return view(
            'admin.current-affairs.index',
            compact(
                'items',
                'counts',
                'status'
            )
        );
    }

    public function show(
        CurrentAffairItem $currentAffair
    ) {
        $currentAffair->load('source');

        return view(
            'admin.current-affairs.show',
            compact('currentAffair')
        );
    }

    public function approve(
        CurrentAffairItem $currentAffair
    ) {
        abort_unless(
            in_array(
                $currentAffair->status,
                ['pending', 'approved'],
                true
            ),
            422
        );

        $currentAffair->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with(
            'success',
            'Current affairs item approved.'
        );
    }

    public function reject(
        CurrentAffairItem $currentAffair
    ) {
        abort_unless(
            $currentAffair->status !== 'processed',
            422
        );

        $currentAffair->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with(
            'success',
            'Current affairs item rejected.'
        );
    }
}
