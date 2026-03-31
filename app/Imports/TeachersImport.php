<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;


class TeachersImport implements ToCollection, WithHeadingRow, WithValidation
{
    public array $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

     public function collection(Collection $rows): void {

        foreach($rows as $index=>$row){
            try{
                DB::transaction(function() use ($row){

                    if (User::where('email', $row['email'])->exists()) {
                        $this->results['skipped']++;
                        return;
                    }
                    
                    $user= User::create([
                        'name'=>$row['name'],
                        'email'=>$row['email'],
                        'password'=>Hash::make($row['teacher_code']),
                        'role'=>'teacher',
                    ]);

                    $teacher=Teacher::create([
                        'user_id'=>$user->id,
                        'name'=>$row['name'],
                        'teacher_code'=>$row['teacher_code'],
                        'department'=>$row['department'],
                    ]);

                    $this->results['created']++;

                });
            }catch(\Throwable $e){
                $this->results['errors'][] = [
                    'row' => $index + 2,
                    'message' => $e->getMessage(),
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
        ];
    }

}
