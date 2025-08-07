@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Daftar Kebijakan TIK Kemlu</h1>

    <a href="{{ route('admin.kebijakan-kemlu.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded mb-4 inline-block">
        Tambah Kebijakan
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
                    <th class="py-3 px-6 text-left">Jenis Kebijakan</th>
                    <th class="py-3 px-6 text-left">Nomor Kebijakan</th>
                    <th class="py-3 px-6 text-left">Tahun Penerbitan</th>
                    <th class="py-3 px-6 text-left">Perihal Kebijakan</th>
                    <th class="py-3 px-6 text-left">Tautan</th>
                    <th class="py-3 px-6 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="text-gray-700 text-sm font-light">
                @foreach($kebijakan as $item)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="py-3 px-6 text-left whitespace-nowrap">{{ $item->jenis_kebijakan }}</td>
                    <td class="py-3 px-6 text-left">{{ $item->nomor_kebijakan }}</td>
                    <td class="py-3 px-6 text-left">{{ $item->tahun_penerbitan }}</td>
                    <td class="py-3 px-6 text-left">{{ $item->perihal_kebijakan }}</td>
                    <td class="py-3 px-6 text-left">
                        @if ($item->tautan)
                            <a href="{{ $item->tautan }}" target="_blank" class="text-blue-500 underline">Lihat Tautan</a>
                        @else
                            <span class="text-gray-500">Tidak ada tautan</span>
                        @endif
                    </td>
                    <td class="py-3 px-6 text-center">
                        <div class="flex item-center justify-center space-x-2">
                            <a href="{{ route('admin.kebijakan-kemlu.show', $item->id) }}" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-3 rounded text-xs">
                                Lihat
                            </a>
                            <a href="{{ route('admin.kebijakan-kemlu.edit', $item->id) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-1 px-3 rounded text-xs">
                                Edit
                            </a>
                            <form action="{{ route('admin.kebijakan-kemlu.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus?')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-1 px-3 rounded text-xs">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $kebijakan->links('pagination::tailwind') }}
    </div>
</div>
@endsection
