<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __invoke(): View
    {
        $order = ['abhyas-plan' => 10, 'taiyari-plan' => 20, 'safalta-plan' => 30, 'varshik-lakshya-plan' => 40];

        $packages = Package::query()
            ->where('is_active', true)
            ->whereIn('slug', array_keys($order))
            ->get()
            ->sortBy(fn (Package $package) => $order[$package->slug])
            ->values();

        $details = [
            'abhyas-plan' => ['Exam, subject and topic-wise test selection', 'Hindi and English questions', 'Instant result and downloadable marksheet', 'Wrong-question reattempt', '2 Current Affairs tests', 'Basic performance report', '7 days support'],
            'taiyari-plan' => ['Exam, subject and topic-wise test selection', 'Hindi and English questions', 'Detailed answer explanations', 'Subject and topic-wise analysis', 'Rank comparison', 'Bookmark and review questions', '10 Current Affairs tests', 'Downloadable marksheet and certificate', '30 days support'],
            'safalta-plan' => ['Exam, subject and topic-wise test selection', 'Hindi and English questions', 'Advanced performance analysis', 'All available Current Affairs tests', 'Rank and progress comparison', 'Unlimited wrong-question reattempt', 'Downloadable marksheet and certificate', 'QR Student ID Card', 'Support throughout validity'],
            'varshik-lakshya-plan' => ['All available exam categories', 'Subject and topic-wise test selection', 'Hindi and English questions', 'Complete yearly performance report', 'Rank and progress comparison', 'All available Current Affairs tests', 'Bookmark, review and unlimited reattempt', 'Downloadable marksheet and certificate', 'QR Student ID Card', '365 days support'],
        ];

        return view('plans.index', compact('packages', 'details'));
    }
}
