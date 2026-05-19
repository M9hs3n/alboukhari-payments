<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\Student;
use App\Services\FeeResolver;
use App\Services\MonthNames;
use Livewire\Component;

class PaymentModal extends Component
{
    public function prefetchModalData(int $studentId, int $year, int $month): array
    {
        $student = Student::findOrFail($studentId);
        return [
            'studentName' => $student->name,
            'due'         => FeeResolver::dueAmount($student, $year, $month),
            'paid'        => FeeResolver::paidAmount($student, $year, $month),
        ];
    }

    public function loadExistingPayments(int $studentId, int $year, int $month): array
    {
        $student = Student::findOrFail($studentId);
        return $student->payments()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('paid_at')
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'amount'       => (float) $p->amount,
                'method'       => $p->method,
                'method_label' => $p->methodLabel(),
                'method_icon'  => $p->methodIcon(),
                'paid_at'      => $p->paid_at->format('Y-m-d'),
                'note'         => $p->note,
            ])
            ->toArray();
    }

    public function savePaymentAlpine(array $payload): array
    {
        $studentId        = (int) ($payload['studentId'] ?? 0);
        $year             = (int) ($payload['year'] ?? 0);
        $month            = (int) ($payload['month'] ?? 0);
        $amount           = (float) ($payload['amount'] ?? 0);
        $method           = $payload['method'] ?? null;
        $note             = $payload['note'] ?? null;
        $paid_at          = $payload['paid_at'] ?? null;
        $editingPaymentId = ! empty($payload['editingPaymentId']) ? (int) $payload['editingPaymentId'] : null;

        if (! $studentId || ! $year || ! $month || ! $paid_at) {
            return ['ok' => false, 'error' => 'missing_required_fields'];
        }
        if (! in_array($method, ['cash', 'bank'], true)) {
            return ['ok' => false, 'error' => 'invalid_method'];
        }
        if ($amount < 0) {
            return ['ok' => false, 'error' => 'amount_must_be_non_negative'];
        }

        if ($editingPaymentId) {
            $p = Payment::findOrFail($editingPaymentId);
            $p->update([
                'amount'  => $amount,
                'method'  => $method,
                'note'    => $note ?: null,
                'paid_at' => $paid_at,
            ]);
        } else {
            Payment::create([
                'student_id'   => $studentId,
                'period_year'  => $year,
                'period_month' => $month,
                'amount'       => $amount,
                'method'       => $method,
                'note'         => $note ?: null,
                'paid_at'      => $paid_at,
            ]);
        }

        $this->dispatch('payment-saved', studentId: $studentId);
        $this->dispatch('flash', message: __('flash.payment_saved'));

        return ['ok' => true];
    }

    public function deletePaymentAlpine(int $paymentId, int $studentId): array
    {
        $p = Payment::findOrFail($paymentId);
        $p->delete();
        $this->dispatch('payment-saved', studentId: $studentId);
        $this->dispatch('flash', message: __('flash.payment_deleted'));

        return ['ok' => true];
    }

    public function render()
    {
        return view('livewire.payment-modal', [
            'monthNames' => MonthNames::full(),
        ]);
    }
}
