<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container mx-auto px-4">
        <div class="bg-white rounded-lg shadow-lg p-6 mt-4">
            <p class="text-gray-600 mb-4">Silahkan pilih menu di atas untuk melihat:</p>
            <div class="grid md:grid-cols-3 gap-4">
                <!-- Kebijakan TIK by Kemlu Section -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold text-blue-800 mb-2">Kebijakan TIK by Kemlu</h2>
                    <p class="text-gray-600">Kebijakan teknologi informasi dan komunikasi yang dikeluarkan oleh Kementerian Luar Negeri.</p>
                    <a href="{{ route('ktbk') }}" class="mt-4 inline-block text-purple-600 hover:text-purple-800">Lihat detail →</a>

                </div>
                <!-- Kebijakan TIK by Non Kemlu Section -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold text-green-800 mb-2">Kebijakan TIK by Non Kemlu</h2>
                    <p class="text-gray-600">Kebijakan teknologi informasi dan komunikasi yang dikeluarkan oleh instansi selain Kemlu.</p>
                    <a href="{{ route('ktbnk') }}" class="mt-4 inline-block text-purple-600 hover:text-purple-800">Lihat detail →</a>

                </div>
                <!-- Nota Kesepahaman (MoU) dan PKS Section -->
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold text-purple-800 mb-2">Nota Kesepahaman (MoU) dan PKS</h2>
                    <p class="text-gray-600">Dokumen kerja sama dan nota kesepahaman dengan berbagai instansi.</p>
                    <a href="{{ route('nkmdp') }}" class="mt-4 inline-block text-purple-600 hover:text-purple-800">Lihat detail →</a>
                </div>
            </div>
        </div>
    </div>
</x-layout>
