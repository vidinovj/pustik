<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container mx-auto px-4 py-8">
        <!-- Filter Form -->
        <form method="GET" action="{{ url('ktbnk') }}" class="bg-white shadow rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <!-- Filter Inputs with Labels -->
                <div>
                    <label for="jenis_kebijakan" class="block text-sm font-medium text-gray-700 mb-1">Jenis Kebijakan</label>
                    <input type="text" id="jenis_kebijakan" name="jenis_kebijakan" value="{{ request('jenis_kebijakan') }}" placeholder="Jenis Kebijakan" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="nomor_kebijakan" class="block text-sm font-medium text-gray-700 mb-1">Nomor Kebijakan</label>
                    <input type="text" id="nomor_kebijakan" name="nomor_kebijakan" value="{{ request('nomor_kebijakan') }}" placeholder="Nomor Kebijakan" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="tahun_penerbitan_min" class="block text-sm font-medium text-gray-700 mb-1">Tahun Penerbitan (awal)</label>
                    <input type="number" id="tahun_penerbitan_min" name="tahun_penerbitan_min" value="{{ request('tahun_penerbitan_min') }}" placeholder="Min Tahun Penerbitan" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="tahun_penerbitan_max" class="block text-sm font-medium text-gray-700 mb-1">Tahun Penerbitan (akhir)</label>
                    <input type="number" id="tahun_penerbitan_max" name="tahun_penerbitan_max" value="{{ request('tahun_penerbitan_max') }}" placeholder="Max Tahun Penerbitan" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                                    <label for="perihal" class="block text-sm font-medium text-gray-700 mb-1">Perihal</label>
                    <input type="text" id="perihal" name="perihal" value="{{ request('perihal') }}" placeholder="Perihal" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">


                <div>
                    <label for="instansi" class="block text-sm font-medium text-gray-700 mb-1">Instansi</label>
                    <input type="text" id="instansi" name="instansi" value="{{ request('instansi') }}" placeholder="Instansi" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <!-- Sort Inputs -->
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Urutkan Berdasarkan</label>
                    <select id="sort_by" name="sort_by" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                        <option value="">Pilih...</option>
                        <option value="nomor_kebijakan" {{ request('sort_by') == 'nomor_kebijakan' ? 'selected' : '' }}>Nomor Kebijakan</option>
                        <option value="tahun_penerbitan" {{ request('sort_by') == 'tahun_penerbitan' ? 'selected' : '' }}>Tahun Penerbitan</option>
                    </select>
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Urutan</label>
                    <select id="sort_order" name="sort_order" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                        <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>Dari bawah ke atas (NAIK)</option>
                        <option value="desc" {{ request('sort_order') == 'desc' ? 'selected' : '' }}>Dari atas ke bawah (TURUN)</option>
                    </select>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mt-6 text-right">
                <button type="submit" class="bg-blue-500 text-white rounded px-4 py-2 hover:bg-blue-600">Filter & Sortir</button>
            </div>
        </form>

        <!-- Table with Filtered and Sorted Data -->
        <div class="overflow-x-auto bg-white shadow rounded-lg">
            <table class="w-full table-auto border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-sm uppercase text-left">
                        <th class="py-3 px-4">Jenis Kebijakan</th>
                        <th class="py-3 px-4">Nomor Kebijakan</th>
                        <th class="py-3 px-4">Tahun Penerbitan</th>
                        <th class="py-3 px-4">Perihal</th>
                        <th class="py-3 px-4">Instansi</th>
                        <th class="py-3 px-4">Tautan</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 text-sm">
                    @forelse($kebijakan as $item)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">{{ $item->jenis_kebijakan }}</td>
                            <td class="py-3 px-4">{{ $item->nomor_kebijakan }}</td>
                            <td class="py-3 px-4">{{ $item->tahun_penerbitan }}</td>
                            <td class="py-3 px-4">{{ $item->perihal }}</td>
                            <td class="py-3 px-4">{{ $item->instansi }}</td>
                            <td class="py-3 px-4">
                                @if ($item->tautan)
                                    <a href="{{ $item->tautan }}" target="_blank" class="text-blue-500 underline">Lihat Tautan</a>
                                @else
                                    <span class="text-gray-500">Tidak ada tautan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 px-4 text-center text-gray-500">Tidak ada data yang ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $kebijakan->links('pagination::tailwind') }}
        </div>
    </div>
</x-layout>
