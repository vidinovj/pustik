@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Daftar Nota Kesepahaman (MoU) dan PKS</h1>

    <a href="{{ route('admin.nota-kesepahaman.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded mb-4 inline-block">
        Tambah Nota Kesepahaman
    </a>

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full bg-white shadow-md rounded-lg overflow-hidden">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">No</th>
                    <th class="py-3 px-6 text-left">MoU/PKS</th>
                    <th class="py-3 px-6 text-left">Perihal Dokumen</th>
                    <th class="py-3 px-6 text-left">Satker Kemlu Terkait</th>
                    <th class="py-3 px-6 text-left">K/L/I External Terkait</th>
                    <th class="py-3 px-6 text-left">Tanggal Disahkan</th>
                    <th class="py-3 px-6 text-left">Tanggal Berakhir</th>
                    <th class="py-3 px-6 text-left">Status</th>
                    <th class="py-3 px-6 text-left">Keterangan</th>
                    <th class="py-3 px-6 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="text-gray-700 text-sm font-light">
                @foreach($notaKesepahaman as $index => $nota)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="py-3 px-6">{{ $index + 1 }}</td>
                    <td class="py-3 px-6">{{ $nota->jenis_dokumen }}</td>
                    <td class="py-3 px-6">{{ $nota->perihal_dokumen }}</td>
                    <td class="py-3 px-6">{{ $nota->satker_kemlu_terkait }}</td>
                    <td class="py-3 px-6">{{ $nota->kl_external_terkait }}</td>
                    <td class="py-3 px-6">{{ $nota->tanggal_disahkan->format('d-m-Y') }}</td>
                    <td class="py-3 px-6">{{ $nota->tanggal_berakhir->format('d-m-Y') }}</td>
                    <td class="py-3 px-6">{{ $nota->status }}</td>
                    <td class="py-3 px-6">{{ $nota->keterangan }}</td>
                    <td class="py-3 px-6 text-center">
                        <a href="{{ route('admin.nota-kesepahaman.show', $nota->id) }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700">Lihat</a>
                        <a href="{{ route('admin.nota-kesepahaman.edit', $nota->id) }}" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-700">Edit</a>
                        <form action="{{ route('admin.nota-kesepahaman.destroy', $nota->id) }}" method="POST" style="display:inline-block;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Yakin ingin menghapus?')" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-700">Hapus</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $notaKesepahaman->links('pagination::tailwind') }}
    </div>
</div>
@endsection
