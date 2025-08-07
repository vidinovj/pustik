@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Nota Kesepahaman</h1>

    <form action="{{ route('admin.nota-kesepahaman.update', $notaKesepahaman->id) }}" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        @csrf
        @method('PUT')
        
        <!-- Sama dengan form pada halaman create, tetapi nilai input sudah terisi -->
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="jenis_dokumen">MoU/PKS</label>
            <select name="jenis_dokumen" id="jenis_dokumen" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                <option value="MOU" {{ $notaKesepahaman->jenis_dokumen == 'MOU' ? 'selected' : '' }}>MoU</option>
                <option value="PKS" {{ $notaKesepahaman->jenis_dokumen == 'PKS' ? 'selected' : '' }}>PKS</option>
            </select>
        </div>

        <!-- Fields lainnya sama dengan create tetapi dengan value pre-filled -->

    </form>
</div>
@endsection
