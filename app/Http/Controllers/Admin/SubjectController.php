<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Subject;

class SubjectController
{
    public function index() {
        return response()->json(['success' => true, 'data' => Subject::all()]);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'subject_code' => ['required', 'string', 'unique:subjects'],
            'subject_name' => ['required', 'string'],
            'credits'      => ['integer', 'min:1'],
        ]);
        return response()->json(['success' => true, 'data' => Subject::create($data)], 201);
    }

    public function show(int $id) {
        return response()->json(['success' => true, 'data' => Subject::findOrFail($id)]);
    }

    public function update(Request $request, int $id) {
        $subject = Subject::findOrFail($id);
        $subject->update($request->validate([
            'subject_name' => ['string'],
            'credits'      => ['integer', 'min:1'],
        ]));
        return response()->json(['success' => true, 'data' => $subject]);
    }

    public function destroy(int $id) {
        Subject::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }
}
