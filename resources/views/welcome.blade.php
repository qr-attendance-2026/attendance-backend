<!DOCTYPE html>

<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>QR Attendance</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>
        body {
            background: #f4f6f9;
        }

        #reader {
            width: 100%;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>

</head>

<body>

    <div class="container mt-5">

        <div class="row justify-content-center">

            <div class="col-md-6">

                <div class="card">

                    <div class="card-header text-center bg-primary text-white">

                        <h4>📷 Điểm Danh Bằng QR</h4>

                    </div>

                    <div class="card-body text-center">

                        <div id="reader"></div>

                        <div id="result" class="mt-3"></div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script>
        function onScanSuccess(decodedText) {

            document.getElementById("result").innerHTML =
                `<div class="alert alert-success">
Mã sinh viên: ${decodedText}
</div>`;

            fetch('/attendance', {

                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },

                    body: JSON.stringify({
                        student_code: decodedText
                    })

                })
                .then(res => res.json())
                .then(data => {

                    alert(data.message)

                })

        }

        let scanner = new Html5QrcodeScanner(
            "reader", {
                fps: 10,
                qrbox: 250
            }
        );

        scanner.render(onScanSuccess);
    </script>

</body>

</html>



