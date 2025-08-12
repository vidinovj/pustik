<x-layout>
    <!-- Menampilkan judul halaman -->
    <x-slot:title>Kebijakan TIK by Kemlu</x-slot:title>

    <div class="container mx-auto px-4 py-6 max-w-screen-xl">
        <!-- Filter Form -->
        <form method="GET" action="{{ url('ktbk') }}" class="mb-6 max-w-full mx-auto">
            <div class="flex space-x-4 mb-4">
                <!-- Filter Jenis Kebijakan -->
                <div class="flex-1">
                    <label for="jenis_kebijakan" class="block text-sm font-medium text-gray-700">Jenis Kebijakan:</label>
                    <input 
                        type="text" 
                        name="jenis_kebijakan" 
                        id="jenis_kebijakan" 
                        value="{{ request('jenis_kebijakan') }}" 
                        placeholder="Jenis Kebijakan" 
                        class="border p-2 w-full rounded-md">
                </div>

                <!-- Filter Nomor Kebijakan -->
                <div class="flex-1">
                    <label for="nomor_kebijakan" class="block text-sm font-medium text-gray-700">Nomor Kebijakan:</label>
                    <input 
                        type="text" 
                        name="nomor_kebijakan" 
                        id="nomor_kebijakan" 
                        value="{{ request('nomor_kebijakan') }}" 
                        placeholder="Nomor Kebijakan" 
                        class="border p-2 w-full rounded-md">
                </div>

                <!-- Filter Tahun Penerbitan (From) -->
                <div class="flex-1">
                    <label for="tahun_penerbitan_from" class="block text-sm font-medium text-gray-700">Tahun Penerbitan (From):</label>
                    <input 
                        type="number" 
                        name="tahun_penerbitan_from" 
                        id="tahun_penerbitan_from" 
                        value="{{ request('tahun_penerbitan_from') }}" 
                        placeholder="Dari Tahun Penerbitan" 
                        class="border p-2 w-full rounded-md">
                </div>

                <!-- Filter Tahun Penerbitan (To) -->
                <div class="flex-1">
                    <label for="tahun_penerbitan_to" class="block text-sm font-medium text-gray-700">Tahun Penerbitan (To):</label>
                    <input 
                        type="number" 
                        name="tahun_penerbitan_to" 
                        id="tahun_penerbitan_to" 
                        value="{{ request('tahun_penerbitan_to') }}" 
                        placeholder="Sampai Tahun Penerbitan" 
                        class="border p-2 w-full rounded-md">
                </div>

                <!-- Filter Perihal Kebijakan -->
                <div class="flex-1">
                    <label for="perihal_kebijakan" class="block text-sm font-medium text-gray-700">Perihal Kebijakan:</label>
                    <input 
                        type="text" 
                        name="perihal_kebijakan" 
                        id="perihal_kebijakan" 
                        value="{{ request('perihal_kebijakan') }}" 
                        placeholder="Perihal Kebijakan" 
                        class="border p-2 w-full rounded-md">
                </div>
            </div>

            <div class="flex space-x-4 mb-4">
                <!-- Sort By Dropdown -->
                <div class="flex-1">
                    <label for="sort_by" class="block text-sm font-medium text-gray-700">Urutkan Berdasarkan:</label>
                    <select 
                        name="sort_by" 
                        id="sort_by" 
                        class="border p-2 w-full rounded-md"
                        onchange="this.form.submit()">
                        <option value="" {{ request('sort_by') == '' ? 'selected' : '' }}>-- Pilih --</option>
                        <option value="tahun_penerbitan" {{ request('sort_by') == 'tahun_penerbitan' ? 'selected' : '' }}>Tahun Penerbitan</option>
                        <option value="nomor_kebijakan" {{ request('sort_by') == 'nomor_kebijakan' ? 'selected' : '' }}>Nomor Kebijakan</option>
                    </select>
                </div>

                <!-- Sort Order Dropdown -->
                <div class="flex-1">
                    <label for="sort_order" class="block text-sm font-medium text-gray-700">Urutan:</label>
                    <select 
                        name="sort_order" 
                        id="sort_order" 
                        class="border p-2 w-full rounded-md"
                        onchange="this.form.submit()">
                        <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>Dari bawah ke atas (NAIK)</option>
                        <option value="desc" {{ request('sort_order') == 'desc' ? 'selected' : '' }}>Dari atas ke bawah (TURUN)</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button 
                        type="submit" 
                        class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600">
                        Filter
                    </button>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto max-w-full mx-auto">
            <table class="w-full bg-white shadow-md rounded-lg overflow-hidden">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Jenis Kebijakan</th>
                        <th class="py-3 px-6 text-left">Nomor Kebijakan</th>
                        <th class="py-3 px-6 text-left">Tahun Penerbitan</th>
                        <th class="py-3 px-6 text-left">Perihal Kebijakan</th>
                        <th class="py-3 px-6 text-left">Tautan</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 text-sm font-light">
                    @forelse($kebijakan as $item)
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-3 px-6 text-left whitespace-nowrap">{{ $item->document_type }}</td>
                            <td class="py-3 px-6 text-left">{{ $item->document_number }}</td>
                            <td class="py-3 px-6 text-left">{{ \Carbon\Carbon::parse($item->issue_date)->year }}</td>
                            <td class="py-3 px-6 text-left break-words">{{ $item->title }}</td>
                            <td class="py-3 px-6 text-left">
                                @if ($item->source_url)
                                    <a 
                                        href="{{ $item->source_url }}" 
                                        target="_blank" 
                                        class="text-blue-500 underline">
                                        Lihat Tautan
                                    </a>
                                @else
                                    <span class="text-gray-500">Tidak ada tautan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">Data kebijakan tidak tersedia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $kebijakan->appends(request()->all())->links('pagination::tailwind') }}
        </div>
    </div>
</x-layout>
