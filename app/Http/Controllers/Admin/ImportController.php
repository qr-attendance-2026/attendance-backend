<?php

namespace App\Http\Controllers\Admin;

use App\Imports\StudentsImport;
use App\Imports\TeachersImport;
use App\Imports\ScheduleImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportController
{
    public function students(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'], //dung lượng file k quá 10MB
        ]);
 
        $import = new StudentsImport();
        Excel::import($import, $request->file('file')); //gọi thư viện excel, đẩy file sang StudentsImport xử lý
 
        return response()->json([
            'success' => true,
            'message' => 'Import thành công.',
            'data'    => $import->results,
        ], 200);
    }


    public function teachers(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new TeachersImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Import thành công.',
            'data'    => $import->results,
        ], 200);
    }

    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $import = new ScheduleImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Import thành công.',
            'data'    => $import->results(),
        ], 200);
    }
}
