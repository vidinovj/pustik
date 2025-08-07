@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Detail Nota Kesepahaman</h1>

    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        <p><strong>MoU/PKS:</strong> {{ $notaKesepahaman->jenis_dokumen }}</p>
        <p><strong>Perihal Dokumen:</strong> {{ $notaKesepahaman->perihal_dokumen }}</p>
        <p><strong>Satker Kemlu Terkait:</strong> {{ $notaKesepahaman->satker_kemlu_terkait }}</p>
        <p><strong>K/L/I External Terkait:</strong> {{ $notaKesepahaman->kl_external_terkait }}</p>
        <p><strong>Tanggal Disahkan:</strong> {{ $notaKesepahaman->tanggal_disahkan->format('d-m-Y') }}</p>
        <p><strong>Tanggal Berakhir:</strong> {{ $notaKesepahaman->tanggal_berakhir->format('d-m-Y') }}</p>
        <p><strong>Status:</strong> {{ $notaKesepahaman->status }}</p>
        <p><strong>Keterangan:</strong> {{ $notaKesepahaman->keterangan }}</p>
    </div>

    <a href="{{ route('admin.nota-kesepahaman.index') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
        Kembali
    </a>
</div>
@endsection
