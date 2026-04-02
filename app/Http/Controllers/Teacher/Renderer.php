<?php

namespace App\Http\Controllers\Teacher;

class Renderer
{
    public function renderSomething($data) 
    {
        // Ví dụ phương thức
        return view('teacher.some_view', ['data' => $data]);
    }
}