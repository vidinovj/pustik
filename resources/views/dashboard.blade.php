<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="container mx-auto px-4">
        <div class="bg-white rounded-lg shadow-lg p-6 mt-4">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">Selamat Datang di Pustik Kemlu</h1>
            <p class="text-gray-600 mb-4">Silahkan pilih menu di atas untuk melihat:</p>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold text-blue-800 mb-2">Kebijakan TIK by Kemlu</h2>
                    <p class="text-gray-600">Kebijakan teknologi informasi dan komunikasi yang dikeluarkan oleh Kementerian Luar Negeri.</p>
                    <a href="{{ route('ktbk') }}" class="mt-4 inline-block text-blue-600 hover:text-blue-800">Lihat detail →</a>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold text-green-800 mb-2">Kebijakan TIK by Non Kemlu</h2>
                    <p class="text-gray-600">Kebijakan teknologi informasi dan komunikasi yang dikeluarkan oleh instansi selain Kemlu.</p>
                    <a href="{{ route('ktbnk') }}" class="mt-4 inline-block text-green-600 hover:text-green-800">Lihat detail →</a>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold text-purple-800 mb-2">Nota Kesepahaman (MoU) dan PKS</h2>
                    <p class="text-gray-600">Dokumen kerja sama dan nota kesepahaman dengan berbagai instansi.</p>
                    <a href="{{ route('nkmdp') }}" class="mt-4 inline-block text-purple-600 hover:text-purple-800">Lihat detail →</a>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold text-yellow-800 mb-2">Manage Legal Documents</h2>
                    <p class="text-gray-600">Manage all legal documents (Kebijakan TIK Kemlu, Non Kemlu, MoU/PKS).</p>
                    <a href="{{ route('admin.legal-documents.index') }}" class="mt-4 inline-block text-yellow-600 hover:text-yellow-800">Go to Management →</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
