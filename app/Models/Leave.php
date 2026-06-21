<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class Leave extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type_id', 'user_id', 'review_by', 'apply_date', 'from_date', 'to_date', 'reason', 'attach', 'note', 'pay_type', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewBy()
    {
        return $this->belongsTo(User::class, 'review_by', 'id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'type_id');
    }


    /**
     * Whole days this leave spans (inclusive of both end dates).
     */
    public function daysCount()
    {
        return (int) ((strtotime($this->to_date) - strtotime($this->from_date)) / 86400) + 1;
    }

    /**
     * Days already used by a staff member for a leave type in a calendar year
     * (by from_date year), counting the given statuses. Used for balance checks.
     *
     * @param  int        $userId
     * @param  int        $typeId
     * @param  int        $year
     * @param  array      $statuses   leave statuses to include (1=approved, 0=pending, 2=rejected)
     * @param  int|null   $excludeId  a leave id to exclude (e.g. the one being approved)
     * @return int
     */
    public static function usedDaysForType($userId, $typeId, $year, array $statuses = [1], $excludeId = null)
    {
        $query = self::where('user_id', $userId)
            ->where('type_id', $typeId)
            ->whereYear('from_date', $year)
            ->whereIn('status', $statuses);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $used = 0;
        foreach ($query->get() as $leave) {
            $used += $leave->daysCount();
        }

        return $used;
    }

    /**
     * Remaining leave days for a staff member for a type in a year.
     * Returns null when the type has no limit (limit <= 0 means unlimited).
     * Counts approved (1) and pending (0) so stacked pending requests can't
     * exceed the cap.
     *
     * @return int|null
     */
    public static function remainingForType(LeaveType $type, $userId, $year)
    {
        if (!$type->limit || $type->limit <= 0) {
            return null; // unlimited
        }

        $used = self::usedDaysForType($userId, $type->id, $year, [1, 0]);

        return max(0, $type->limit - $used);
    }


    // Paid leave count in a month
    public static function paid_leave($id, $month, $year)
    {
        $paid_leave = 0;

        $lfroms = Leave::where('user_id', $id)->where('status', 1)->whereMonth('from_date', $month)->whereYear('from_date', $year)->get();
        if(isset($lfroms)){
            foreach ($lfroms as $lfrom) {
                if($lfrom->to_date <= date("Y-m-t", strtotime($year.'-'.$month.'-01'))){
                    if($lfrom->pay_type == 1){
                        $paid_leave = $paid_leave + (int)((strtotime($lfrom->to_date) - strtotime($lfrom->from_date))/86400) + 1;
                    }
                }
                else{
                    if($lfrom->pay_type == 1){
                        $paid_leave = $paid_leave + (int)((strtotime(date("Y-m-t", strtotime($year.'-'.$month.'-01'))) - strtotime($lfrom->from_date))/86400) + 1;
                    }
                }
            }
        }


        $ltos = Leave::where('user_id', $id)->where('status', 1)->whereMonth('to_date', $month)->whereYear('to_date', $year)->get();
        if(isset($ltos)){
            foreach ($ltos as $lto) {
                if($lto->from_date >= date("Y-m-d", strtotime($year.'-'.$month.'-01'))){
                    //
                }
                else{
                    if($lto->pay_type == 1){
                        $paid_leave = $paid_leave + (int)((strtotime($lto->to_date) - strtotime(date("Y-m-d", strtotime($year.'-'.$month.'-01'))))/86400) + 1;
                    }
                }
            }
        }

        return $paid_leave;
    }


    // Unpaid leave count in a month
    public static function unpaid_leave($id, $month, $year)
    {
        $unpaid_leave = 0;

        $lfroms = Leave::where('user_id', $id)->where('status', 1)->whereMonth('from_date', $month)->whereYear('from_date', $year)->get();
        if(isset($lfroms)){
            foreach ($lfroms as $lfrom) {
                if($lfrom->to_date <= date("Y-m-t", strtotime($year.'-'.$month.'-01'))){
                    if($lfrom->pay_type == 2){
                        $unpaid_leave = $unpaid_leave + (int)((strtotime($lfrom->to_date) - strtotime($lfrom->from_date))/86400) + 1;
                    }
                }
                else{
                    if($lfrom->pay_type == 2){
                        $unpaid_leave = $unpaid_leave + (int)((strtotime(date("Y-m-t", strtotime($year.'-'.$month.'-01'))) - strtotime($lfrom->from_date))/86400) + 1;
                    }
                }
            }
        }


        $ltos = Leave::where('user_id', $id)->where('status', 1)->whereMonth('to_date', $month)->whereYear('to_date', $year)->get();
        if(isset($ltos)){
            foreach ($ltos as $lto) {
                if($lto->from_date >= date("Y-m-d", strtotime($year.'-'.$month.'-01'))){
                    //
                }
                else{
                    if($lto->pay_type == 2){
                        $unpaid_leave = $unpaid_leave + (int)((strtotime($lto->to_date) - strtotime(date("Y-m-d", strtotime($year.'-'.$month.'-01'))))/86400) + 1;
                    }
                }
            }
        }

        return $unpaid_leave;
    }
}
