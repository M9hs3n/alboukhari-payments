<?php

namespace App\Livewire;

use App\Models\Student;
use App\Models\StudentMonthlyFeeOverride;
use App\Models\StudentSurcharge;
use App\Models\StudentSuspension;
use App\Services\FeeResolver;
use App\Services\MonthNames;
use App\Services\MonthStatusResolver;
use Livewire\Component;

class StudentPanel extends Component
{
    public ?int $studentId = null;
    public string $tab = 'payments'; // payments | settings | notes | siblings

    // For edit
    public string $name = '';
    public string $phone_primary_raw = '';
    public string $phone_secondary_raw = '';
    public ?float $default_fee_amount = null;
    public string $notes = '';

    // Suspension
    public string $suspend_starts_at = '';
    public string $suspend_ends_at = '';
    public string $suspend_reason = '';

    // Fee Override
    public int $override_month = 1;
    public ?float $override_amount = null;
    public string $override_reason = '';

    // Surcharge
    public int $surcharge_month = 1;
    public ?float $surcharge_amount = null;
    public string $surcharge_reason = '';

    protected $listeners = [
        'close-panels' => 'close',
        'open-student-panel' => 'switchStudent',
        'payment-saved' => '$refresh',
    ];

    public function mount(int $studentId)
    {
        $this->loadStudent($studentId);
    }

    public function switchStudent(int $studentId)
    {
        $this->loadStudent($studentId);
        $this->tab = 'payments';
    }

    private function loadStudent(int $id)
    {
        $student = Student::with(['family.students', 'payments', 'markers', 'suspensions'])->findOrFail($id);
        $this->studentId = $student->id;
        $this->name = $student->name;
        $this->phone_primary_raw = $student->phone_primary_raw ?? '';
        $this->phone_secondary_raw = $student->phone_secondary_raw ?? '';
        $this->default_fee_amount = $student->default_fee_amount ? (float) $student->default_fee_amount : null;
        $this->notes = $student->notes ?? '';
    }

    public function close()
    {
        $this->dispatch('close-student');
        $this->studentId = null;
    }

    public function saveBasic()
    {
        $student = Student::findOrFail($this->studentId);
        $student->name = trim($this->name);
        $student->phone_primary_raw = $this->phone_primary_raw ?: null;
        $student->phone_primary_e164 = \App\Support\PhoneNormalizer::normalize($this->phone_primary_raw);
        $student->phone_secondary_raw = $this->phone_secondary_raw ?: null;
        $student->phone_secondary_e164 = \App\Support\PhoneNormalizer::normalize($this->phone_secondary_raw);
        $student->default_fee_amount = $this->default_fee_amount;
        $student->notes = $this->notes ?: null;
        $student->save();

        $this->dispatch('flash', message: __('common.flash_saved'));
    }

    public function toggleFlag(string $flag)
    {
        $allowed = ['is_hidden', 'is_blocked_messages', 'is_in_person', 'excluded_from_send_all', 'included_in_send_all', 'allow_sms', 'allow_whatsapp'];
        if (!in_array($flag, $allowed, true)) return;
        $student = Student::findOrFail($this->studentId);
        $student->{$flag} = !$student->{$flag};
        $student->save();
        $this->dispatch('flash', message: __('common.flash_updated'));
    }

    public function addSuspension()
    {
        $this->validate([
            'suspend_starts_at' => 'required|date',
            'suspend_ends_at' => 'nullable|date|after_or_equal:suspend_starts_at',
            'suspend_reason' => 'nullable|string|max:255',
        ]);

        StudentSuspension::create([
            'student_id' => $this->studentId,
            'starts_at' => $this->suspend_starts_at,
            'ends_at' => $this->suspend_ends_at ?: null,
            'reason' => $this->suspend_reason ?: null,
        ]);

        $this->suspend_starts_at = '';
        $this->suspend_ends_at = '';
        $this->suspend_reason = '';
        $this->dispatch('flash', message: __('flash.suspension_added'));
    }

    public function removeSuspension(int $id)
    {
        StudentSuspension::where('id', $id)->where('student_id', $this->studentId)->delete();
        $this->dispatch('flash', message: __('common.flash_deleted'));
    }

    public function addOverride()
    {
        $this->validate([
            'override_month' => 'required|integer|min:1|max:12',
            'override_amount' => 'required|numeric|min:0',
            'override_reason' => 'nullable|string|max:255',
        ]);

        $year = (int) date('Y');
        StudentMonthlyFeeOverride::updateOrCreate(
            [
                'student_id' => $this->studentId,
                'period_year' => $year,
                'period_month' => $this->override_month,
            ],
            [
                'amount' => $this->override_amount,
                'reason' => $this->override_reason ?: null,
            ]
        );

        $this->override_amount = null;
        $this->override_reason = '';
        $this->dispatch('flash', message: __('flash.fee_override_saved'));
    }

    public function removeOverride(int $id)
    {
        StudentMonthlyFeeOverride::where('id', $id)->where('student_id', $this->studentId)->delete();
        $this->dispatch('flash', message: __('common.flash_deleted'));
    }

    public function addSurcharge()
    {
        $this->validate([
            'surcharge_month' => 'required|integer|min:1|max:12',
            'surcharge_amount' => 'required|numeric|min:0',
            'surcharge_reason' => 'required|string|max:255',
        ]);

        $year = (int) date('Y');
        StudentSurcharge::create([
            'student_id' => $this->studentId,
            'period_year' => $year,
            'period_month' => $this->surcharge_month,
            'amount' => $this->surcharge_amount,
            'reason' => $this->surcharge_reason,
        ]);

        $this->surcharge_amount = null;
        $this->surcharge_reason = '';
        $this->dispatch('flash', message: __('flash.surcharge_added'));
    }

    public function removeSurcharge(int $id)
    {
        StudentSurcharge::where('id', $id)->where('student_id', $this->studentId)->delete();
        $this->dispatch('flash', message: __('common.flash_deleted'));
    }

    public function openPayment(int $month)
    {
        $this->dispatch('open-payment-modal', studentId: $this->studentId, year: (int) date('Y'), month: $month);
    }

    public function render()
    {
        $student = Student::with(['family.students', 'payments', 'markers', 'suspensions'])->findOrFail($this->studentId);
        $year = (int) date('Y');
        $months = MonthNames::full();

        $monthsData = [];
        $totalBalance = 0;
        foreach (range(1, 12) as $m) {
            $status = MonthStatusResolver::resolve($student, $year, $m);
            $due = FeeResolver::dueAmount($student, $year, $m);
            $paid = FeeResolver::paidAmount($student, $year, $m);
            $payments = $student->payments->where('period_year', $year)->where('period_month', $m);
            if (in_array($status, ['unpaid', 'late', 'partial'])) {
                $totalBalance += ($due - $paid);
            }
            $monthsData[$m] = compact('status', 'due', 'paid', 'payments');
        }

        $siblings = $student->siblings();

        return view('livewire.student-panel', [
            'student' => $student,
            'siblings' => $siblings,
            'months' => $months,
            'monthsData' => $monthsData,
            'totalBalance' => $totalBalance,
            'year' => $year,
        ]);
    }
}
