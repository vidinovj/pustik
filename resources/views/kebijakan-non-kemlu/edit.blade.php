@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Kebijakan TIK Non Kemlu</h1>

    <form action="{{ route('admin.kebijakan-non-kemlu.update', $kebijakan->id) }}" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label for="jenis_kebijakan" class="block text-gray-700 text-sm font-bold mb-2">Jenis Kebijakan</label>
            <input type="text" name="jenis_kebijakan" id="jenis_kebijakan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required value="{{ old('jenis_kebijakan', $kebijakan->jenis_kebijakan) }}">
        </div>

        <div class="mb-4">
            <label for="nomor_kebijakan" class="block text-gray-700 text-sm font-bold mb-2">Nomor Kebijakan</label>
            <input type="text" name="nomor_kebijakan" id="nomor_kebijakan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required value="{{ old('nomor_kebijakan', $kebijakan->nomor_kebijakan) }}">
        </div>

        <div class="mb-4">
            <label for="tahun_penerbitan" class="block text-gray-700 text-sm font-bold mb-2">Tahun Penerbitan</label>
            <input type="number" name="tahun_penerbitan" id="tahun_penerbitan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required value="{{ old('tahun_penerbitan', $kebijakan->tahun_penerbitan) }}">
        </div>

        <div class="mb-4">
            <label for="perihal" class="block text-gray-700 text-sm font-bold mb-2">Perihal Kebijakan</label>
            <textarea name="perihal" id="perihal" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>{{ old('perihal', $kebijakan->perihal) }}</textarea>
        </div>

        <div class="mb-4">
            <label for="instansi" class="block text-gray-700 text-sm font-bold mb-2">Instansi</label>
            <input type="text" name="instansi" id="instansi" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required value="{{ old('instansi', $kebijakan->instansi) }}">
        </div>

        <div class="mb-4">
            <label for="tautan" class="block text-gray-700 text-sm font-bold mb-2">Tautan</label>
            <input type="url" name="tautan" id="tautan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="{{ old('tautan', $kebijakan->tautan) }}">
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Perbarui
            </button>
            <a href="{{ route('admin.kebijakan-non-kemlu.index') }}" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
