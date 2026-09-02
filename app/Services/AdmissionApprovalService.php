<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdmissionApprovalService
{
    public function approve(
        AdmissionApplication $application,
        User $admin
    ): array {
        return DB::transaction(function () use ($application, $admin) {
            if ($application->status !== 'submitted') {
                throw new \RuntimeException(
                    'Only submitted applications can be approved.'
                );
            }

            $plainPassword = Str::password(10);

            $user = User::firstOrCreate(
                ['email' => $application->email],
                [
                    'name' => $application->name,
                    'password' => Hash::make($plainPassword),
                    'role' => 'student',
                    'is_active' => true,
                    'email_verified_at' => $application->email_verified_at,
                ]
            );

            $studentCode = $this->studentCode();

            $profile = StudentProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'student_code' => $studentCode,
                    'phone' => $application->phone,
                    'photo_path' => $application->photo_path,
                    'date_of_birth' => $application->date_of_birth,
                    'gender' => $application->gender,
                    'address' => $application->address,
                    'city' => $application->city,
                    'district' => $application->district,
                    'state' => $application->state,
                    'pincode' => $application->pincode,
                    'email_verified_at' => $application->email_verified_at,
                    'admission_approved_at' => now(),
                    'status' => 'active',
                ]
            );

            $application->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'created_user_id' => $user->id,
            ]);

            return [
                'user' => $user,
                'profile' => $profile,
                'temporary_password' => $plainPassword,
            ];
        });
    }

    private function studentCode(): string
    {
        do {
            $code = 'MCI'.now()->format('y').random_int(100000, 999999);
        } while (
            StudentProfile::where('student_code', $code)->exists()
        );

        return $code;
    }
}
