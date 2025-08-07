@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Detail Kebijakan TIK Kemlu</h1>

    <div class="mb-3">
        <strong>Jenis Kebijakan:</strong>
        {{ $kebijakan->jenis_kebijakan }}
    </div>

    <div class="mb-3">
        <strong>Nomor Kebijakan:</strong>
        {{ $kebijakan->nomor_kebijakan }}
    </div>

    <div class="mb-3">
        <strong>Tahun Penerbitan:</strong>
        {{ $kebijakan->tahun_penerbitan }}
    </div>

    <div class="mb-3">
        <strong>Perihal Kebijakan:</strong>
        {{ $kebijakan->perihal_kebijakan }}
    </div>

    @if($kebijakan->tautan)
        <div class="mb-3">
            <strong>Tautan:</strong>
            <a href="{{ $kebijakan->tautan }}" target="_blank">{{ $kebijakan->tautan }}</a>
        </div>
    @endif

    <a href="{{ route('admin.kebijakan-kemlu.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Kembali</a>
</div>
@endsection
