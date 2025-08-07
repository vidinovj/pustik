@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Edit Kebijakan TIK Kemlu</h1>

    <form action="{{ route('admin.kebijakan-kemlu.update', $kebijakan->id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="mb-3">
            <label for="jenis_kebijakan" class="form-label">Jenis Kebijakan</label>
            <input type="text" class="form-control" id="jenis_kebijakan" name="jenis_kebijakan" value="{{ $kebijakan->jenis_kebijakan }}" required>
        </div>

        <div class="mb-3">
            <label for="nomor_kebijakan" class="form-label">Nomor Kebijakan</label>
            <input type="text" class="form-control" id="nomor_kebijakan" name="nomor_kebijakan" value="{{ $kebijakan->nomor_kebijakan }}" required>
        </div>

        <div class="mb-3">
            <label for="tahun_penerbitan" class="form-label">Tahun Penerbitan</label>
            <input type="number" class="form-control" id="tahun_penerbitan" name="tahun_penerbitan" value="{{ $kebijakan->tahun_penerbitan }}" required min="1900" max="{{ date('Y') + 1 }}">
        </div>

        <div class="mb-3">
            <label for="perihal_kebijakan" class="form-label">Perihal Kebijakan</label>
            <textarea class="form-control" id="perihal_kebijakan" name="perihal_kebijakan" required>{{ $kebijakan->perihal_kebijakan }}</textarea>
        </div>

        <div class="mb-3">
            <label for="tautan" class="form-label">Tautan (Opsional)</label>
            <input type="url" class="form-control" id="tautan" name="tautan" value="{{ $kebijakan->tautan }}">
        </div>

        <button type="submit" class="btn btn-primary">Perbarui</button>
    </form>
</div>
@endsection
