<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Excel Student Import & QR Generation</title>
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen text-gray-800 font-sans">
    <div class="container mx-auto px-4 py-10 max-w-4xl">
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8 border border-gray-100">
            <h1 class="text-3xl font-bold mb-2 text-gray-800">Student Upload Tester</h1>
            <p class="text-gray-500 mb-8">Upload an Excel file (.xlsx, .csv) with the appropriate template to test the student import logic and generate their corresponding QR codes.</p>
            
            <form id="uploadForm" class="mb-4">
                <div class="mb-5">
                    <label class="block text-gray-700 text-sm font-semibold mb-2" for="bearerToken">
                        Admin Bearer Token
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-300" id="bearerToken" type="text" placeholder="eyJh... (Paste your admin token here)" required>
                    <p class="text-xs text-gray-500 mt-2">Required for authenticating to the production API.</p>
                </div>
                
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:bg-gray-50 transition-colors">
                    <input type="file" id="file" name="file" accept=".xlsx,.xls,.csv" class="hidden">
                    <label for="file" class="cursor-pointer flex flex-col items-center justify-center space-y-3">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <span id="fileName" class="text-gray-600 font-medium font-semibold text-lg">Click to select an Excel file</span>
                        <span class="text-sm text-gray-400">or drag and drop</span>
                    </label>
                </div>
                
                <div class="mt-6 flex items-center justify-between">
                    <button type="submit" id="submitBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-colors w-full sm:w-auto h-12 flex items-center justify-center">
                        <span id="btnText">Upload & Generate QR</span>
                        <div id="loader" class="loader ml-3 hidden" style="width: 20px; height: 20px; border-width: 3px;"></div>
                    </button>
                    <a href="/test-import" class="text-blue-600 hover:underline text-sm font-medium">Reset Page</a>
                </div>
            </form>
            
            <div id="errorMessage" class="hidden mt-4 p-4 bg-red-100 text-red-700 rounded-lg border border-red-200">
            </div>
            
        </div>

        <div id="resultsSection" class="hidden">
            <h2 class="text-2xl font-bold mb-4 text-gray-800 flex items-center">
                <svg class="w-6 h-6 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Import Results
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white p-6 rounded-xl shadow border border-green-100 flex flex-col items-center justify-center">
                    <span class="text-4xl font-bold text-green-600" id="statCreated">0</span>
                    <span class="text-gray-500 font-medium uppercase text-sm mt-1">Created Successfully</span>
                </div>
                <div class="bg-white p-6 rounded-xl shadow border border-yellow-100 flex flex-col items-center justify-center">
                    <span class="text-4xl font-bold text-yellow-500" id="statSkipped">0</span>
                    <span class="text-gray-500 font-medium uppercase text-sm mt-1">Skipped (Existed)</span>
                </div>
                <div class="bg-white p-6 rounded-xl shadow border border-red-100 flex flex-col items-center justify-center">
                    <span class="text-4xl font-bold text-red-500" id="statErrors">0</span>
                    <span class="text-gray-500 font-medium uppercase text-sm mt-1">Errors Found</span>
                </div>
            </div>

            <!-- Generated Students & QR Codes -->
            <div id="studentsContainer" class="hidden mb-8">
                <h3 class="text-xl font-bold mb-4 text-gray-800">Generated Profiles & QR Codes</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6" id="studentsList">
                    <!-- Cards will be dynamically inserted here -->
                </div>
            </div>

            <!-- Error List -->
            <div id="errorDetailsContainer" class="hidden">
                <h3 class="text-xl font-bold mb-4 text-gray-800 text-red-600">Import Errors Log</h3>
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Row</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Description</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="errorDetailsList">
                            <!-- Error rows here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('file');
        const fileNameDisplay = document.getElementById('fileName');
        const form = document.getElementById('uploadForm');
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileNameDisplay.textContent = this.files[0].name;
                fileNameDisplay.classList.add('text-blue-600');
            } else {
                fileNameDisplay.textContent = 'Click to select an Excel file';
                fileNameDisplay.classList.remove('text-blue-600');
            }
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const file = fileInput.files[0];
            if (!file) {
                showError('Please select a file first.');
                return;
            }

            // UI Reset & Loading State
            document.getElementById('errorMessage').classList.add('hidden');
            document.getElementById('resultsSection').classList.add('hidden');
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').classList.add('opacity-75', 'cursor-not-allowed');
            document.getElementById('btnText').textContent = 'Processing...';
            document.getElementById('loader').classList.remove('hidden');

            const token = document.getElementById('bearerToken').value;

            const formData = new FormData();
            formData.append('file', file);
            
            try {
                // Hitting the Google Cloud production route
                const response = await fetch('https://api-attendance-backend-520975280881.asia-southeast1.run.app/api/admin/import/students', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token
                    }
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    displayResults(result.data);
                } else {
                    let errMsg = result.message || 'An error occurred on the server.';
                    if (result.errors) {
                        errMsg += ' ' + Object.values(result.errors).flat().join(', ');
                    }
                    showError(errMsg);
                }
            } catch (error) {
                showError('Network error or invalid response. Check console for details. ' + error.message);
                console.error('Import Error:', error);
            } finally {
                // Restore button state
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('submitBtn').classList.remove('opacity-75', 'cursor-not-allowed');
                document.getElementById('btnText').textContent = 'Upload & Generate QR';
                document.getElementById('loader').classList.add('hidden');
            }
        });

        function displayResults(data) {
            document.getElementById('resultsSection').classList.remove('hidden');
            
            // Set Stats
            document.getElementById('statCreated').textContent = data.created || 0;
            document.getElementById('statSkipped').textContent = data.skipped || 0;
            document.getElementById('statErrors').textContent = (data.errors && data.errors.length) || 0;

            // Render Students with QR Codes
            const studentsContainer = document.getElementById('studentsContainer');
            const studentsList = document.getElementById('studentsList');
            studentsList.innerHTML = '';
            
            if (data.students && data.students.length > 0) {
                studentsContainer.classList.remove('hidden');
                data.students.forEach(student => {
                    const card = document.createElement('div');
                    card.className = 'bg-white rounded-xl shadow border border-gray-100 overflow-hidden hover:shadow-md transition-shadow';
                    
                    const qrImage = student.qr_code_path 
                        ? `<img src="${student.qr_code_path}" alt="QR Code" class="w-full h-auto object-contain bg-white p-4">`
                        : `<div class="p-8 text-center bg-gray-50 text-gray-400">No QR Generated</div>`;

                    card.innerHTML = `
                        <div class="h-48 border-b border-gray-100 flex items-center justify-center p-2 bg-gray-50">
                            ${qrImage}
                        </div>
                        <div class="p-4">
                            <h4 class="font-bold text-gray-800 text-lg mb-1 truncate" title="${student.name}">${student.name}</h4>
                            <p class="text-sm border flex items-center justify-center py-1 mt-2 text-indigo-700 bg-indigo-50 border-indigo-200 rounded font-semibold tracking-wide">${student.student_code}</p>
                            <p class="text-xs text-gray-500 mt-2 truncate" title="${student.email}">${student.email}</p>
                        </div>
                    `;
                    studentsList.appendChild(card);
                });
            } else {
                studentsContainer.classList.add('hidden');
            }

            // Render Errors
            const errorContainer = document.getElementById('errorDetailsContainer');
            const errorList = document.getElementById('errorDetailsList');
            errorList.innerHTML = '';
            
            if (data.errors && data.errors.length > 0) {
                errorContainer.classList.remove('hidden');
                data.errors.forEach(err => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Row ${err.row || 'Unknown'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">${err.email || 'N/A'}</td>
                        <td class="px-6 py-4 text-sm text-red-600 break-words">${err.reason || 'Unknown error'}</td>
                    `;
                    errorList.appendChild(tr);
                });
            } else {
                errorContainer.classList.add('hidden');
            }
        }

        function showError(message) {
            const errDiv = document.getElementById('errorMessage');
            errDiv.textContent = message;
            errDiv.classList.remove('hidden');
            document.getElementById('resultsSection').classList.add('hidden');
        }
    </script>
</body>
</html>
