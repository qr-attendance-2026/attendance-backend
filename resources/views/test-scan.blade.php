<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Teacher Test: QR Code Scanner</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- HTML5-QRCode Library for camera scanning -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #10b981; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen text-gray-800 font-sans">
    <div class="container mx-auto px-4 py-10 max-w-5xl">
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8 border border-gray-100">
            <h1 class="text-3xl font-bold mb-2 text-gray-800">Teacher QR Scanner Test</h1>
            <p class="text-gray-500 mb-8">Upload a student's QR code image. The system will extract the data and simulate a teacher checking in the student.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-4">
                
                <!-- Left side: Form -->
                <div>
                    <div class="mb-5">
                        <label class="block text-gray-700 text-sm font-semibold mb-2" for="bearerToken">
                            Teacher Bearer Token
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-green-300" id="bearerToken" type="text" placeholder="eyJh... (Paste your teacher token here)" required>
                        <p class="text-xs text-gray-500 mt-2">Required for authenticating to the production API.</p>
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-gray-700 text-sm font-semibold mb-2" for="sessionId">
                            Target Session ID
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-green-300" id="sessionId" type="number" value="1" placeholder="Enter session ID">
                        <p class="text-xs text-gray-500 mt-2">Make sure this Session ID exists in your `attendance_sessions` table.</p>
                    </div>

                    <div class="mb-5">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">
                            Scan Student QR Code
                        </label>
                        <div id="reader" class="rounded-xl overflow-hidden border-2 border-gray-300"></div>
                        <p class="text-xs text-gray-500 mt-2">Allow camera permissions if prompted.</p>
                        <button id="resetScannerBtn" class="mt-3 text-sm text-blue-600 hover:text-blue-800 hidden font-semibold">Rescan QR Code</button>
                    </div>
                    
                    <button id="scanBtn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-colors w-full h-12 flex items-center justify-center space-x-2 hidden">
                        <span id="btnText">Process Attendance</span>
                        <div id="loader" class="loader hidden"></div>
                    </button>
                    
                    <div id="statusAlert" class="hidden mt-4 p-4 rounded-lg border text-sm font-semibold"></div>
                </div>

                <!-- Right side: Decoded data -->
                <div class="bg-gray-50 rounded-xl border p-6 flex flex-col items-start justify-start h-full">
                    
                    <div id="decodedData" class="w-full hidden">
                        <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">Extracted Data:</h4>
                        <pre id="jsonBlock" class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm w-full font-mono"></pre>
                    </div>

                    <div id="scanPrompt" class="text-gray-400 flex flex-col items-center justify-center w-full h-full min-h-[250px]">
                        <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span>Waiting for QR Code scan...</span>
                    </div>
                </div>

            </div>
            
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h2 class="text-xl font-bold text-gray-800">Attendance Records Table</h2>
                <span class="text-sm text-gray-500">Live preview of database inserts</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">id</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">session_id</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">student_id</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">status</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">method</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">checked_at</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">is_makeup</th>
                        </tr>
                    </thead>
                    <tbody id="recordsTable" class="bg-white divide-y divide-gray-200 font-mono text-sm">
                        <!-- Entries will go here -->
                        <tr id="emptyRow">
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400 font-sans">No scans performed in this session yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const decodedDataSection = document.getElementById('decodedData');
        const jsonBlock = document.getElementById('jsonBlock');
        const scanPrompt = document.getElementById('scanPrompt');
        const scanBtn = document.getElementById('scanBtn');
        const recordsTable = document.getElementById('recordsTable');
        const emptyRow = document.getElementById('emptyRow');
        const statusAlert = document.getElementById('statusAlert');
        const resetScannerBtn = document.getElementById('resetScannerBtn');
        
        let currentStudentCode = null;
        let html5QrcodeScanner = null;

        function onScanSuccess(decodedText, decodedResult) {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.pause();
                resetScannerBtn.classList.remove('hidden');
            }
            
            scanPrompt.classList.add('hidden');
            statusAlert.classList.add('hidden');
            
            try {
                const parsed = JSON.parse(decodedText);
                jsonBlock.textContent = JSON.stringify(parsed, null, 2);
                currentStudentCode = parsed.student_code;
                
                decodedDataSection.classList.remove('hidden');
                scanBtn.classList.remove('hidden');
                
                showAlert('QR Code extracted! Click Process Attendance to submit.', 'success');
            } catch (err) {
                jsonBlock.textContent = "RAW FORMAT:\n" + decodedText;
                currentStudentCode = decodedText; // fallback if it's just raw text
                decodedDataSection.classList.remove('hidden');
                scanBtn.classList.remove('hidden');
                showAlert('Extracted non-JSON data from QR.', 'warning');
            }
        }

        function onScanFailure(error) {
            // handle scan failure, usually better to ignore and keep scanning
        }

        document.addEventListener("DOMContentLoaded", function() {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                { fps: 10, qrbox: {width: 250, height: 250} },
                /* verbose= */ false);
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        });

        resetScannerBtn.addEventListener('click', function() {
            if (html5QrcodeScanner) html5QrcodeScanner.resume();
            currentStudentCode = null;
            decodedDataSection.classList.add('hidden');
            scanBtn.classList.add('hidden');
            scanPrompt.classList.remove('hidden');
            this.classList.add('hidden');
            statusAlert.classList.add('hidden');
        });

        scanBtn.addEventListener('click', async function() {
            if (!currentStudentCode) return;
            
            const sessionId = document.getElementById('sessionId').value;
            if (!sessionId) {
                showAlert('Please enter a target Session ID', 'error');
                return;
            }

            const token = document.getElementById('bearerToken').value;
            if (!token) {
                showAlert('Please enter your Teacher Bearer Token.', 'error');
                return;
            }
            
            // UI state
            scanBtn.disabled = true;
            document.getElementById('btnText').textContent = 'Processing...';
            document.getElementById('loader').classList.remove('hidden');
            statusAlert.classList.add('hidden');
            
            try {
                const response = await fetch('https://api-attendance-backend-520975280881.asia-southeast1.run.app/api/teacher/attendance/scan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        student_code: currentStudentCode
                    })
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    showAlert('Attendance processed successfully!', 'success');
                    appendRecordRow(result.data);
                } else {
                    showAlert(result.message || 'Error occurred while saving.', 'error');
                }
            } catch (error) {
                showAlert('Network error. Check console.', 'error');
                console.error(error);
            } finally {
                scanBtn.disabled = false;
                document.getElementById('btnText').textContent = 'Process Attendance';
                document.getElementById('loader').classList.add('hidden');
            }
        });

        function appendRecordRow(record) {
            if (emptyRow) emptyRow.style.display = 'none'; // hide empty row indicator
            
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-green-50 transition-colors animate-pulse bg-green-100'; // light highlight initially
            
            setTimeout(() => { tr.className = 'hover:bg-gray-50 transition-colors'; }, 1000);
            
            tr.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-gray-900">${record.id || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-900">${record.session_id || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-900">${record.student_id || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-900"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">${record.status || 'present'}</span></td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-500">${record.method || 'qr'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-500">${record.checked_at ? new Date(record.checked_at).toISOString().replace('T', ' ').substring(0, 19) : '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-500">${(record.is_makeup ? 1 : 0)}</td>
            `;
            
            recordsTable.prepend(tr); // Add to top
        }

        function showAlert(msg, type) {
            statusAlert.textContent = msg;
            statusAlert.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'border-red-200', 'bg-green-100', 'text-green-700', 'border-green-200', 'bg-yellow-100', 'text-yellow-700', 'border-yellow-200');
            
            if (type === 'error') {
                statusAlert.classList.add('bg-red-100', 'text-red-700', 'border-red-200');
            } else if (type === 'success') {
                statusAlert.classList.add('bg-green-100', 'text-green-700', 'border-green-200');
            } else {
                statusAlert.classList.add('bg-yellow-100', 'text-yellow-700', 'border-yellow-200');
            }
        }
    </script>
</body>
</html>
