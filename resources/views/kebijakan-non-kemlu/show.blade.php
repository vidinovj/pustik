@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Detail Kebijakan TIK Non Kemlu</h1>

    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <div class="mb-4">
            <h2 class="text-lg font-semibold">Jenis Kebijakan:</h2>
            <p>{{ $kebijakan->jenis_kebijakan }}</p>
        </div>

        <div class="mb-4">
            <h2 class="text-lg font-semibold">Nomor Kebijakan:</h2>
            <p>{{ $kebijakan->nomor_kebijakan }}</p>
        </div>

        <div class="mb-4">
            <h2 class="text-lg font-semibold">Tahun Penerbitan:</h2>
            <p>{{ $kebijakan->tahun_penerbitan }}</p>
        </div>

        <div class="mb-4">
            <h2 class="text-lg font-semibold">Perihal Kebijakan:</h2>
            <p>{{ $kebijakan->perihal }}</p>
        </div>

        <div class="mb-4">
            <h2 class="text-lg font-semibold">Instansi:</h2>
            <p>{{ $kebijakan->instansi }}</p>
        </div>

        <div class="mb-4">
            <h2 class="text-lg font-semibold">Tautan:</h2>
            @if ($kebijakan->tautan)
                <a href="{{ $kebijakan->tautan }}" target="_blank" class="text-blue-500 underline">Lihat Tautan</a>
            @else
                <p class="text-gray-500">Tidak ada tautan</p>
            @endif
        </div>

        <a href="{{ route('admin.kebijakan-non-kemlu.index') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Kembali</a>
    </div>
</div>
@endsection
