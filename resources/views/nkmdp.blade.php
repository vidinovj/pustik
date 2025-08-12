<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container mx-auto px-4 py-8">
        <!-- Page Title -->

        <!-- Filter Form -->
        <form method="GET" action="{{ url('nkmdp') }}" class="bg-white shadow rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <!-- Filter Inputs with Labels -->
                <div>
                    <label for="jenis_dokumen" class="block text-sm font-medium text-gray-700 mb-1">Jenis Dokumen</label>
                    <input type="text" id="jenis_dokumen" name="jenis_dokumen" value="{{ request('jenis_dokumen') }}" placeholder="Jenis Dokumen" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="perihal_dokumen" class="block text-sm font-medium text-gray-700 mb-1">Perihal Dokumen</label>
                    <input type="text" id="perihal_dokumen" name="perihal_dokumen" value="{{ request('perihal_dokumen') }}" placeholder="Perihal Dokumen" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="satker_kemlu_terkait" class="block text-sm font-medium text-gray-700 mb-1">Satker Kemlu Terkait</label>
                    <input type="text" id="satker_kemlu_terkait" name="satker_kemlu_terkait" value="{{ request('satker_kemlu_terkait') }}" placeholder="Satker Kemlu Terkait" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="kl_external_terkait" class="block text-sm font-medium text-gray-700 mb-1">K/L/I External Terkait</label>
                    <input type="text" id="kl_external_terkait" name="kl_external_terkait" value="{{ request('kl_external_terkait') }}" placeholder="K/L/I External Terkait" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <!-- Date Range Filters -->
                <div>
                    <label for="start_date_disahkan" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Disahkan (Awal)</label>
                    <input type="date" id="start_date_disahkan" name="start_date_disahkan" value="{{ request('start_date_disahkan') }}" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="end_date_disahkan" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Disahkan (Akhir)</label>
                    <input type="date" id="end_date_disahkan" name="end_date_disahkan" value="{{ request('end_date_disahkan') }}" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="start_date_berakhir" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Berakhir (Awal)</label>
                    <input type="date" id="start_date_berakhir" name="start_date_berakhir" value="{{ request('start_date_berakhir') }}" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="end_date_berakhir" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Berakhir (Akhir)</label>
                    <input type="date" id="end_date_berakhir" name="end_date_berakhir" value="{{ request('end_date_berakhir') }}" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200">
                </div>

                <!-- Sorting Options -->
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sortir Berdasarkan</label>
                    <select id="sort_by" name="sort_by" class="border border-gray-300 rounded p-2 w-full focus:ring focus:ring-blue-200" onchange="this.form.submit()">
                        <option value="" disabled selected>Pilih</option>
                        <option value="tanggal_disahkan_asc" {{ request('sort_by') == 'tanggal_disahkan_asc' ? 'selected' : '' }}>Tanggal Disahkan (Terbaru)</option>
                        <option value="tanggal_disahkan_desc" {{ request('sort_by') == 'tanggal_disahkan_desc' ? 'selected' : '' }}>Tanggal Disahkan (Terlama)</option>
                        <option value="tanggal_berakhir_asc" {{ request('sort_by') == 'tanggal_berakhir_asc' ? 'selected' : '' }}>Tanggal Berakhir (Terbaru)</option>
                        <option value="tanggal_berakhir_desc" {{ request('sort_by') == 'tanggal_berakhir_desc' ? 'selected' : '' }}>Tanggal Berakhir (Terlama)</option>
                    </select>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mt-6 text-right">
                <button type="submit" class="bg-blue-500 text-white rounded px-4 py-2 hover:bg-blue-600">Filter & Sortir</button>
            </div>
        </form>

        <!-- Table with Filtered Data -->
        <div class="overflow-x-auto bg-white shadow rounded-lg">
            <table class="w-full table-auto border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-sm uppercase text-left">
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">MoU/PKS</th>
                        <th class="py-3 px-4">Perihal Dokumen</th>
                        <th class="py-3 px-4">Satker Kemlu Terkait</th>
                        <th class="py-3 px-4">K/L/I External Terkait</th>
                        <th class="py-3 px-4">Tanggal Disahkan</th>
                        <th class="py-3 px-4">Tanggal Berakhir</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 text-sm">
                    @forelse($notaKesepahaman as $index => $nota)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">{{ $index + 1 }}</td>
                            <td class="py-3 px-4">{{ $nota->document_type }}</td>
                            <td class="py-3 px-4 break-words">{{ $nota->title }}</td>
                            <td class="py-3 px-4">{{ $nota->metadata['satker_kemlu_terkait'] ?? '' }}</td>
                            <td class="py-3 px-4">{{ $nota->metadata['kl_external_terkait'] ?? '' }}</td>
                            <td class="py-3 px-4">{{ \Carbon\Carbon::parse($nota->issue_date)->format('d-m-Y') }}</td>
                            <td class="py-3 px-4">{{ \Carbon\Carbon::parse($nota->metadata['tanggal_berakhir'])->format('d-m-Y') ?? '' }}</td>
                            <td class="py-3 px-4">{{ $nota->status }}</td>
                            <td class="py-3 px-4">{{ $nota->keterangan }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4 px-4 text-center text-gray-500">Tidak ada data yang ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $notaKesepahaman->links('pagination::tailwind') }}
        </div>
    </div>
</x-layout>
