@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="max-w-md mx-auto mt-8">
    <div class="bg-white p-8 rounded-lg shadow">
        <h2 class="text-2xl font-bold mb-6 text-center">Create Account</h2>

        <form method="POST" action="{{ route('store-register') }}">
            @csrf

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="first_name">
                    First Name
                </label>
                <input class="w-full px-3 py-2 border rounded" id="first_name" name="first_name" type="text" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="last_name">
                    Last Name
                </label>
                <input class="w-full px-3 py-2 border rounded" id="last_name" name="last_name" type="text" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                    Email Address
                </label>
                <input class="w-full px-3 py-2 border rounded" id="email" name="email" type="email" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="student_id">
                    Student ID (Optional)
                </label>
                <input class="w-full px-3 py-2 border rounded" id="student_id" name="student_id" type="text">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                    Password
                </label>
                <input class="w-full px-3 py-2 border rounded" id="password" name="password" type="password" required>
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password_confirmation">
                    Confirm Password
                </label>
                <input class="w-full px-3 py-2 border rounded" id="password_confirmation" 
                    name="password_confirmation" type="password" required>
            </div>

            <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700">
                Register
            </button>
        </form>

        <p class="mt-4 text-center text-sm">
            Already have an account? 
            <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Login here</a>
        </p>
    </div>
</div>
@endsection