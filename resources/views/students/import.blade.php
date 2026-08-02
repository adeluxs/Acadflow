@extends('layouts.app')

@section('title', 'Import Students')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">Import Students</h1>

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('import_errors'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <p class="font-bold">Import Errors:</p>
                <ul class="list-disc list-inside text-sm">
                    @foreach (session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-2">CSV Format Requirements</h2>
                <p class="text-sm text-gray-600 mb-2">Your CSV file must contain the following columns:</p>
                <code class="block bg-gray-100 p-3 rounded text-sm">
                    first_name, last_name, email, student_id, phone, password
                </code>
                <p class="text-sm text-gray-600 mt-2">
                    The <code class="bg-gray-100 px-1 rounded">password</code> column is optional. Default is <code class="bg-gray-100 px-1 rounded">password123</code>.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.students.import.post') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>

                <div class="mb-4">
                    <label for="department_id" class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select name="department_id" id="department_id" required
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Department</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-6">
                    <label for="semester_id" class="block text-sm font-medium text-gray-700 mb-2">Semester (Optional - for auto-enrollment)</label>
                    <select name="semester_id" id="semester_id"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Semester</option>
                        @foreach(\App\Models\Semester::all() as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->name }} - {{ $semester->academicSession->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('admin.dashboard') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Import Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
