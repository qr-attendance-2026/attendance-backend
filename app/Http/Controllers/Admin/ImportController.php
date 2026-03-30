<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
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
 
    // teachers() and schedule() follow the same pattern — create
    // TeachersImport and ScheduleImport classes similarly.

}
