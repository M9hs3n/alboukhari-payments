<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\Student;
use App\Services\FeeResolver;
use App\Services\MonthNames;
use App\Services\MonthStatusResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->input('year') ?: date('Y'));
        $month = (int) ($request->input('month') ?: date('n'));

        // ملخص اليوم
        $todayMessages = MessageLog::whereDate('created_at', today())->count();
        $todayMessagesCost = (float) MessageLog::whereDate('created_at', today())->sum('cost');
        $todayPayments = (float) Payment::whereDate('created_at', today())->whereIn('method', ['cash', 'bank'])->sum('amount');

        // إجمالي الشهر
        $monthPaidCash = (float) Payment::where('period_year', $year)->where('period_month', $month)->where('method', 'cash')->sum('amount');
        $monthPaidBank = (float) Payment::where('period_year', $year)->where('period_month', $month)->where('method', 'bank')->sum('amount');
        $monthTotal = $monthPaidCash + $monthPaidBank;

        // المتأخرون الآن
        $overdue = [];
        $allStudents = Student::canMessage()->get();
        foreach ($allStudents as $st) {
            $status = MonthStatusResolver::resolve($st, $year, $month);
            if (in_array($status, ['unpaid', 'late', 'partial'])) {
                $bal = FeeResolver::balance($st, $year, $month);
                $overdue[] = [
                    'student' => $st,
                    'status' => $status,
                    'balance' => $bal,
                ];
            }
        }
        usort($overdue, fn($a, $b) => $b['balance'] <=> $a['balance']);

        // أكثر المتأخرين (تراكم سنوي)
        $topDebtors = [];
        foreach (Student::canMessage()->get() as $st) {
            $totalBal = 0;
            $monthsBehind = 0;
            for ($m = 1; $m <= 12; $m++) {
                $status = MonthStatusResolver::resolve($st, $year, $m);
                if (in_array($status, ['unpaid', 'late', 'partial'])) {
                    $totalBal += FeeResolver::balance($st, $year, $m);
                    if (in_array($status, ['unpaid', 'late'])) $monthsBehind++;
                }
            }
            if ($monthsBehind >= 2) {
                $topDebtors[] = compact('st', 'totalBal', 'monthsBehind');
            }
        }
        usort($topDebtors, fn($a, $b) => $b['totalBal'] <=> $a['totalBal']);
        $topDebtors = array_slice($topDebtors, 0, 30);

        // التحصيل الشهري للسنة
        $monthlyTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            $cash = (float) Payment::where('period_year', $year)->where('period_month', $m)->where('method', 'cash')->sum('amount');
            $bank = (float) Payment::where('period_year', $year)->where('period_month', $m)->where('method', 'bank')->sum('amount');
            $monthlyTotals[$m] = ['cash' => $cash, 'bank' => $bank, 'total' => $cash + $bank];
        }

        // تكلفة الرسائل بالشهر
        $messagesCostByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $messagesCostByMonth[$m] = (float) MessageLog::whereYear('created_at', $year)
                ->whereMonth('created_at', $m)
                ->sum('cost');
        }

        $months = MonthNames::full();

        return view('reports', compact(
            'year', 'month', 'months',
            'todayMessages', 'todayMessagesCost', 'todayPayments',
            'monthPaidCash', 'monthPaidBank', 'monthTotal',
            'overdue', 'topDebtors', 'monthlyTotals', 'messagesCostByMonth'
        ));
    }
}
