@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Edit Kebijakan TIK Kemlu</h1>

    <form action="{{ route('admin.kebijakan-kemlu.update', $kebijakan->id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="mb-4">
            <label for="jenis_kebijakan" class="block text-gray-700 text-sm font-bold mb-2">Jenis Kebijakan</label>
            <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="jenis_kebijakan" name="jenis_kebijakan" value="{{ $kebijakan->jenis_kebijakan }}" required>
        </div>

        <div class="mb-4">
            <label for="nomor_kebijakan" class="block text-gray-700 text-sm font-bold mb-2">Nomor Kebijakan</label>
            <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="nomor_kebijakan" name="nomor_kebijakan" value="{{ $kebijakan->nomor_kebijakan }}" required>
        </div>

        <div class="mb-4">
            <label for="tahun_penerbitan" class="block text-gray-700 text-sm font-bold mb-2">Tahun Penerbitan</label>
            <input type="number" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="tahun_penerbitan" name="tahun_penerbitan" value="{{ $kebijakan->tahun_penerbitan }}" required min="1900" max="{{ date('Y') + 1 }}">
        </div>

        <div class="mb-4">
            <label for="perihal_kebijakan" class="block text-gray-700 text-sm font-bold mb-2">Perihal Kebijakan</label>
            <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="perihal_kebijakan" name="perihal_kebijakan" required>{{ $kebijakan->perihal_kebijakan }}</textarea>
        </div>

        <div class="mb-4">
            <label for="tautan" class="block text-gray-700 text-sm font-bold mb-2">Tautan (Opsional)</label>
            <input type="url" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="tautan" name="tautan" value="{{ $kebijakan->tautan }}">
        </div>

        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Perbarui</button>
    </form>
</div>
@endsection
