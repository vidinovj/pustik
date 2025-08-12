{{-- resources/views/home.blade.php --}}
<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="container-fluid px-4 py-3">
        <div class="bg-white rounded shadow-sm p-4">
            <p class="text-muted mb-4">Silahkan pilih menu di atas untuk melihat:</p>
            
            <div class="row g-4">
                <!-- Kebijakan TIK by Kemlu Section -->
                <div class="col-md-4">
                    <div class="card h-100 border-0" style="background-color: #dbeafe;">
                        <div class="card-body">
                            <h2 class="h5 fw-semibold mb-3" style="color: #1e40af;">Kebijakan TIK by Kemlu</h2>
                            <p class="text-muted">Kebijakan teknologi informasi dan komunikasi yang dikeluarkan oleh Kementerian Luar Negeri.</p>
                            <a href="{{ route('ktbk') }}" class="btn btn-outline-primary btn-sm mt-auto">
                                Lihat detail →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Kebijakan TIK by Non Kemlu Section -->
                <div class="col-md-4">
                    <div class="card h-100 border-0" style="background-color: #dcfce7;">
                        <div class="card-body">
                            <h2 class="h5 fw-semibold mb-3" style="color: #166534;">Kebijakan TIK by Non Kemlu</h2>
                            <p class="text-muted">Kebijakan teknologi informasi dan komunikasi yang dikeluarkan oleh instansi selain Kemlu.</p>
                            <a href="{{ route('ktbnk') }}" class="btn btn-outline-primary btn-sm mt-auto">
                                Lihat detail →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Nota Kesepahaman (MoU) dan PKS Section -->
                <div class="col-md-4">
                    <div class="card h-100 border-0" style="background-color: #f3e8ff;">
                        <div class="card-body">
                            <h2 class="h5 fw-semibold mb-3" style="color: #7c3aed;">Nota Kesepahaman (MoU) dan PKS</h2>
                            <p class="text-muted">Dokumen kerja sama dan nota kesepahaman dengan berbagai instansi.</p>
                            <a href="{{ route('nkmdp') }}" class="btn btn-outline-primary btn-sm mt-auto" style="border-color: #7c3aed; color: #7c3aed;">
                                Lihat detail →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>