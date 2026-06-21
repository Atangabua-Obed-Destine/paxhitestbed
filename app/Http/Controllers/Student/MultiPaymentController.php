<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MultiPayment;
use App\Models\MultiPaymentDistribution;
use App\Models\Fee;
use App\Models\Student;
use App\Models\PrintSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Flasher\Prime\FlasherInterface;

class MultiPaymentController extends Controller
{
    /**
     * Store a new multi-payment
     */
    public function store(Request $request, FlasherInterface $flasher)
    {
        // Validation
        $request->validate([
            'selected_fees' => 'required|string',
            'distribution_data' => 'nullable|string',
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'payment_date' => 'required|date|before_or_equal:today',
            'transaction_id' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            // Get student
            $student = Auth::guard('student')->user();
            
            // Decode selected fees
            $selectedFeeIds = json_decode($request->selected_fees, true);
            
            if (empty($selectedFeeIds) || !is_array($selectedFeeIds)) {
                throw new \Exception('Invalid fee selection');
            }

            // Get all selected fees with their current balances
            $fees = Fee::whereIn('id', $selectedFeeIds)
                ->where('student_enroll_id', '!=', null)
                ->whereHas('studentEnroll', function($query) use ($student) {
                    $query->where('student_id', $student->id);
                })
                ->get();

            if ($fees->count() === 0) {
                throw new \Exception('No valid fees found');
            }

            // Calculate total balance and validate
            $totalBalance = 0;
            $feeBalances = [];

            foreach ($fees as $fee) {
                $balance = $this->calculateFeeBalance($fee);
                
                if ($balance <= 0) {
                    continue; // Skip fully paid fees
                }
                
                $feeBalances[$fee->id] = [
                    'fee' => $fee,
                    'balance' => $balance,
                    'due_date' => $fee->due_date
                ];
                
                $totalBalance += $balance;
            }

            if (empty($feeBalances)) {
                throw new \Exception('All selected fees are already paid');
            }

            $paymentAmount = (float) $request->payment_amount;

            if ($paymentAmount > $totalBalance) {
                $paymentAmount = $totalBalance;
            }

            // Upload receipt
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $fileName = time() . '_' . $student->id . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $receiptPath = $file->storeAs('multi-payments', $fileName, 'public');
            }

            // Create multi-payment record
            $multiPayment = MultiPayment::create([
                'student_id' => $student->id,
                'total_amount' => $totalBalance,
                'amount_paid' => $paymentAmount,
                'payment_method' => $request->payment_method,
                'transaction_id' => $request->transaction_id,
                'receipt_path' => $receiptPath,
                'status' => 'pending',
                'payment_date' => $request->payment_date,
                'admin_note' => $request->note
            ]);

            // Check if we have custom distribution data from frontend
            $distributionData = null;
            if ($request->filled('distribution_data')) {
                $distributionData = json_decode($request->distribution_data, true);
            }

            // Distribute payment
            if ($distributionData && is_array($distributionData)) {
                // Use custom distribution from frontend (includes installment-level distribution)
                foreach ($distributionData as $distItem) {
                    if (!isset($distItem['feeId']) || !isset($distItem['amountApplied'])) {
                        continue;
                    }
                    
                    $amountToApply = (float) $distItem['amountApplied'];
                    
                    if ($amountToApply <= 0) {
                        continue; // Skip items with no payment
                    }
                    
                    $feeId = $distItem['feeId'];
                    
                    if (!isset($feeBalances[$feeId])) {
                        continue; // Skip invalid fee IDs
                    }
                    
                    $fee = $feeBalances[$feeId]['fee'];
                    $balanceBefore = (float) $distItem['currentBalance'];
                    $balanceAfter = (float) $distItem['remainingBalance'];
                    
                    // Determine fee status after payment
                    $feeStatusAfter = $balanceAfter <= 0 ? 'paid' : 'partial';
                    
                    // Create distribution record with installment info if present
                    MultiPaymentDistribution::create([
                        'multi_payment_id' => $multiPayment->id,
                        'fee_id' => $feeId,
                        'installment_id' => $distItem['installmentId'] ?? null,
                        'fee_amount' => $balanceBefore,
                        'amount_applied' => $amountToApply,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'fee_status_after' => $feeStatusAfter
                    ]);
                }
            } else {
                // Fallback: Auto-distribute payment across fees (prioritize by due date - oldest first)
                $remainingAmount = $paymentAmount;
                
                // Sort fees by due date
                uasort($feeBalances, function($a, $b) {
                    return strtotime($a['due_date']) - strtotime($b['due_date']);
                });

                foreach ($feeBalances as $feeId => $feeData) {
                    if ($remainingAmount <= 0) {
                        break;
                    }

                    $fee = $feeData['fee'];
                    $balanceBefore = $feeData['balance'];
                    
                    // Calculate amount to apply to this fee
                    $amountToApply = min($remainingAmount, $balanceBefore);
                    $balanceAfter = $balanceBefore - $amountToApply;
                    
                    // Determine fee status after payment
                    $feeStatusAfter = $balanceAfter <= 0 ? 'paid' : 'partial';
                    
                    // Create distribution record
                    MultiPaymentDistribution::create([
                        'multi_payment_id' => $multiPayment->id,
                        'fee_id' => $feeId,
                        'installment_id' => null,
                        'fee_amount' => $balanceBefore,
                        'amount_applied' => $amountToApply,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'fee_status_after' => $feeStatusAfter
                    ]);

                    $remainingAmount -= $amountToApply;
                }
            }

            DB::commit();

            $flasher->addSuccess('Multi-payment submitted successfully! Your payment is pending admin verification.');
            
            return redirect()->route('student.multi-payment.show', $multiPayment->id);

        } catch (\Exception $e) {
            DB::rollback();
            
            // Delete uploaded file if exists
            if (isset($receiptPath) && $receiptPath) {
                Storage::disk('public')->delete($receiptPath);
            }

            $flasher->addError('Failed to process payment: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Show multi-payment details
     */
    public function show($id)
    {
        $student = Auth::guard('student')->user();
        
        $payment = MultiPayment::with(['distributions.fee.category', 'distributions.fee.studentEnroll'])
            ->where('student_id', $student->id)
            ->findOrFail($id);

        $data = [
            'title' => 'Multi-Payment Details',
            'payment' => $payment,
            'setting' => PrintSetting::where('slug', 'fees-receipt')->first(),
        ];

        return view('student.multi-payment.show', $data);
    }

    /**
     * List all multi-payments for student
     */
    public function index()
    {
        $student = Auth::guard('student')->user();
        
        $payments = MultiPayment::with(['distributions'])
            ->where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $data = [
            'title' => 'My Multi-Payments',
            'payments' => $payments,
            'setting' => PrintSetting::where('slug', 'fees-receipt')->first(),
        ];

        return view('student.multi-payment.index', $data);
    }

    /**
     * Calculate current balance for a fee
     */
    private function calculateFeeBalance($fee)
    {
        $today = date('Y-m-d');
        
        if ($fee->status == 0) {
            // Calculate discount
            $discount_amount = 0;
            if (isset($fee->category)) {
                foreach ($fee->category->discounts->where('status', '1') as $discount) {
                    $availability = \App\Models\FeesDiscount::availability($discount->id, $fee->studentEnroll->student_id);
                    if (isset($availability) && $discount->start_date <= $today && $discount->end_date >= $today) {
                        if ($discount->type == '1') {
                            $discount_amount += $discount->amount;
                        } else {
                            $discount_amount += ($fee->fee_amount / 100) * $discount->amount;
                        }
                    }
                }
            }
            
            // Calculate fine
            $fine_amount = 0;
            if (empty($fee->pay_date) || $fee->due_date < $fee->pay_date) {
                $due_date = strtotime($fee->due_date);
                $today_time = strtotime($today);
                $days = (int)(($today_time - $due_date) / 86400);
                
                if ($fee->due_date < $today && isset($fee->category)) {
                    foreach ($fee->category->fines->where('status', '1') as $fine) {
                        if ($fine->start_day <= $days && $fine->end_day >= $days) {
                            if ($fine->type == '1') {
                                $fine_amount += $fine->amount;
                            } else {
                                $fine_amount += ($fee->fee_amount / 100) * $fine->amount;
                            }
                        }
                    }
                }
            }
            
            $net_amount = ($fee->fee_amount - $discount_amount) + $fine_amount;
        } else {
            $net_amount = $fee->total_amount;
        }
        
        $amount_paid = $fee->paid_amount ?? 0;
        return $net_amount - $amount_paid;
    }
}
