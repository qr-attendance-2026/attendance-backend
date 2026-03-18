<!DOCTYPE html>
<html>

<head>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

<h2>Danh sách điểm danh</h2>

<table class="table table-bordered">

<tr>
<th>ID</th>
<th>Mã SV</th>
<th>Thời gian</th>
</tr>

@foreach($data as $row)

<tr>

<td>{{$row->id}}</td>
<td>{{$row->student_code}}</td>
<td>{{$row->time}}</td>

</tr>

@endforeach

</table>

</div>

</body>

</html>