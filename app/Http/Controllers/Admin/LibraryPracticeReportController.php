<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LibraryPracticeReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $period = $filters['month'] ?? CarbonImmutable::now('Asia/Kolkata')->format('Y-m');
        $rows = DB::table('library_practice_months as m')->join('library_practice_accounts as a', 'a.id', '=', 'm.library_practice_account_id')
            ->select('a.student_name', 'a.student_code', 'a.branch_id', 'm.period')
            ->selectSub(DB::table('library_practice_selections as s')->selectRaw('COUNT(*)')->whereColumn('s.library_practice_month_id', 'm.id'), 'selected_count')
            ->selectSub(DB::table('library_practice_selections as s')->selectRaw('COUNT(*)')->whereColumn('s.library_practice_month_id', 'm.id')->whereNotNull('s.test_attempt_id'), 'started_count')
            ->selectSub(DB::table('library_practice_selections as s')->join('test_attempts as t', 't.id', '=', 's.test_attempt_id')->selectRaw('COUNT(*)')->whereColumn('s.library_practice_month_id', 'm.id')->where('t.status', 'evaluated'), 'completed_count')
            ->where('m.period', $period)->orderBy('a.student_name')->paginate(50)->withQueryString();

        return view('admin.library-practice',compact('rows','period'));
    }
}
