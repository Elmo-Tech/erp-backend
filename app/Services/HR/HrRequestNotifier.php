<?php

namespace App\Services\HR;

use App\Models\User;
use App\Notifications\HrRequestNotification;
use App\Policies\UserAdministrationPolicy;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HrRequestNotifier
{
    public function send(Model $record, string $kind, string $permission): void
    {
        $employee = User::find($record->employee_id);
        if (! $employee) {
            return;
        }
        $needsReview = $kind === 'leave' ? $record->status === null : $record->status === 'pending';
        $event = $kind === 'leave' && $needsReview ? 'submitted' : $record->status;
        $data = [
            'type' => $kind.'_'.$event, 'request_id' => $record->id,
            'employee_id' => $employee->id, 'employee_name' => $employee->name,
            'status' => $record->status,
            'message' => $needsReview ? $employee->name.' submitted a '.$kind.' request' : 'Your '.$kind.' request was '.$record->status,
            'body' => $kind === 'leave' ? $record->start_date->toDateString().' to '.$record->end_date->toDateString() : $record->permission_date->toDateString(),
            'url' => $needsReview ? '/dashboard/employees/'.$employee->id : '/dashboard/profile',
        ];
        if (! $needsReview) {
            $employee->notify(new HrRequestNotification($data));

            return;
        }
        // Use the same permission/hierarchy predicate as approval, not role names.
        User::where('is_active', true)->where('id', '!=', $employee->id)->each(function (User $reviewer) use ($employee, $permission, $data): void {
            $resource = substr($permission, strlen('manage_'));
            $canReview = $reviewer->hasAnyPermission(['manage_employees', $permission, 'approve_'.$resource, 'reject_'.$resource]);
            try {
                app(UserAdministrationPolicy::class)->authorizeHrReadTarget($reviewer, $employee);
            } catch (HttpException $exception) {
                return;
            }
            if ($canReview) {
                $reviewer->notify(new HrRequestNotification($data));
            }
        });
    }
}
