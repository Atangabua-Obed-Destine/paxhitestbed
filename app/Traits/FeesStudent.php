<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Transaction;
use App\Models\Fee;
use Carbon\Carbon;

trait FeesStudent {

    /**
     * Calculate the net amount for a fee.
     * @param Request $request
     */
    public function netAmount($fee_id)
    {
        //
        $fee = Fee::find($fee_id);

        // For partially paid fees, return remaining balance
        if($fee->status == 2) {
            $total_amount = $fee->total_amount ?? 0;
            $paid_amount = $fee->paid_amount ?? 0;
            return max(0, $total_amount - $paid_amount);
        }

        // For unpaid fees, calculate dynamically
        // Discount Calculation
        $discount_amount = 0;
        $today = date('Y-m-d');

        if(isset($fee->category)){
        foreach($fee->category->discounts->where('status', '1') as $discount){

        $availability = \App\Models\FeesDiscount::availability($discount->id, $fee->studentEnroll->student_id);

            if(isset($availability)){
            if($discount->start_date <= $today && $discount->end_date >= $today){
                if($discount->type == '1'){
                    $discount_amount = $discount_amount + $discount->amount;
                }
                else{
                    $discount_amount = $discount_amount + ( ($fee->fee_amount / 100) * $discount->amount);
                }
            }}
        }}


        // Fine Calculation
        $fine_amount = 0;
        if(empty($fee->pay_date) || $fee->due_date < $fee->pay_date){

            $due_date = strtotime($fee->due_date);
            $today = strtotime(date('Y-m-d'));
            $days = (int)(($today - $due_date)/86400);

            if($fee->due_date < date("Y-m-d")){
                if(isset($fee->category)){
                foreach($fee->category->fines->where('status', '1') as $fine){
                if($fine->start_day <= $days && $fine->end_day >= $days){
                    if($fine->type == '1'){
                        $fine_amount = $fine_amount + $fine->amount;
                    }
                    else{
                        $fine_amount = $fine_amount + ( ($fee->fee_amount / 100) * $fine->amount);
                    }
                }
                }}
            }
        }


        // Net Amount Calculation
        $net_amount = ($fee->fee_amount - $discount_amount) + $fine_amount;

        return $net_amount;
    }

    /**
     * Pay Student Fee
     * @param Request $request
     */
    public function payStudentFee($fee_id, $method)
    {
        //
        $fee = Fee::find($fee_id);

        // For partially paid fees, use stored values and accumulate
        if($fee->status == 2) {
            $discount_amount = $fee->discount_amount ?? 0;
            $fine_amount = $fee->fine_amount ?? 0;
            $net_amount = $fee->total_amount ?? 0;
            $payment_amount = max(0, $net_amount - ($fee->paid_amount ?? 0));
            
            DB::beginTransaction();
            
            // Accumulate payment
            $current_paid = $fee->paid_amount ?? 0;
            $total_paid = $current_paid + $payment_amount;
            
            // Determine status
            if ($total_paid >= $net_amount) {
                $status = '1'; // Fully paid
                $total_paid = $net_amount; // Cap at net amount
            } else {
                $status = '2'; // Still partially paid
            }
            
            // Update fee
            $fee->paid_amount = $total_paid;
            $fee->pay_date = Carbon::today();
            $fee->payment_method = $method;
            $fee->status = $status;
            $fee->updated_by = Auth::guard('student')->user()->id ?? null;
            $fee->save();
            
            // Transaction
            $transaction = new Transaction;
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $payment_amount; // Record actual payment amount
            $transaction->type = '1';
            $transaction->created_by = Auth::guard('student')->user()->id ?? null;
            $fee->studentEnroll->student->transactions()->save($transaction);
            DB::commit();
            
            return;
        }

        // For unpaid fees, calculate dynamically
        // Discount Calculation
        $discount_amount = 0;
        $today = date('Y-m-d');

        if(isset($fee->category)){
        foreach($fee->category->discounts->where('status', '1') as $discount){

        $availability = \App\Models\FeesDiscount::availability($discount->id, $fee->studentEnroll->student_id);

            if(isset($availability)){
            if($discount->start_date <= $today && $discount->end_date >= $today){
                if($discount->type == '1'){
                    $discount_amount = $discount_amount + $discount->amount;
                }
                else{
                    $discount_amount = $discount_amount + ( ($fee->fee_amount / 100) * $discount->amount);
                }
            }}
        }}


        // Fine Calculation
        $fine_amount = 0;
        if(empty($fee->pay_date) || $fee->due_date < $fee->pay_date){

            $due_date = strtotime($fee->due_date);
            $today = strtotime(date('Y-m-d'));
            $days = (int)(($today - $due_date)/86400);

            if($fee->due_date < date("Y-m-d")){
                if(isset($fee->category)){
                foreach($fee->category->fines->where('status', '1') as $fine){
                if($fine->start_day <= $days && $fine->end_day >= $days){
                    if($fine->type == '1'){
                        $fine_amount = $fine_amount + $fine->amount;
                    }
                    else{
                        $fine_amount = $fine_amount + ( ($fee->fee_amount / 100) * $fine->amount);
                    }
                }
                }}
            }
        }


        // Net Amount Calculation
        $net_amount = ($fee->fee_amount - $discount_amount) + $fine_amount;


        DB::beginTransaction();
        // Update Data
        $fee->discount_amount = $discount_amount;
        $fee->fine_amount = $fine_amount;
        $fee->paid_amount = $net_amount;
        $fee->pay_date = Carbon::today();
        $fee->payment_method = $method;
        $fee->status = '1';
        $fee->updated_by = Auth::guard('student')->user()->id ?? null;
        $fee->save();


        // Transaction
        $transaction = new Transaction;
        $transaction->transaction_id = Str::random(16);
        $transaction->amount = $net_amount;
        $transaction->type = '1';
        $transaction->created_by = Auth::guard('student')->user()->id ?? null;
        $fee->studentEnroll->student->transactions()->save($transaction);
        DB::commit();

        return $success = true;
    }
}
