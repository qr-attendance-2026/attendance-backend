<?php

namespace App\Imports;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeachersImport implements ToCollection, WithHeadingRow, WithValidation
{
     public array $results = ['created' => 0, 'skipped' => 0, 'errors' => []];
 
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            try {
                DB::transaction(function () use ($row) {
 
                    // Skip if email already exists
                    if (User::where('email', $row['email'])->exists()) {
                        $this->results['skipped']++;
                        return;
                    }

                    $user = User::create([
                        'name'=>$row['name'],
                        'email' => $row['email'],
                        'password' => Hash::make($row['teacher_code']),
                        'role' => 'teacher',
                    ]);
                    
                    $teacher = Teacher::create([
                        'user_id' => $user->id,
                        'teacher_code' => $row['teacher_code'],
                        'department' => $row['department'],
                        
                    ]);
                
                    $this->results['created']++;

                });
                
            } catch (\Throwable $e) {
                $this->results['errors'][] = [
                    'row' => $index + 2, // +1 for 0-based index, +1 for header
                    'email'  => $row['email'] ?? '?',
                    'reason' => $e->getMessage(),

                ];
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.teacher_code' => ['required', 'string'],
            '*.name'         => ['required', 'string'],
            '*.email'        => ['required', 'email'],
            '*.department'   => ['required', 'string'],
        ];
    }


}
