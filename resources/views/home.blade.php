{{-- resources/views/home.blade.php --}}
<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>
    
    <div class="d-flex flex-column align-items-center justify-content-center min-vh-100 pb-4 pt-2">
        <div class="text-center text-white mb-5">
            <h1 class="display-4 fw-bold text-shadow">Sistem Manajemen Dokumen Kebijakan TIK Pustik KP</h1>
        </div>

        <div class="w-100" style="max-width: 1100px;">
            <!-- Menu Cards Container -->
            <div class="bg-white rounded-3 shadow-lg p-4 {{ request()->is('/') ? 'bg-opacity-95' : '' }}">                
                <div class="row g-3 justify-content-center">
                    <!-- Kebijakan TIK by Kemlu Section -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border-0 shadow-sm hover-card" style="background-color: #dbeafe;">
                            <div class="card-body d-flex flex-column p-3">
                                <div class="text-center mb-2">
                                    <i class="fas fa-building fa-2x mb-2" style="color: #1e40af;"></i>
                                </div>
                                <h2 class="h6 fw-semibold mb-2 text-center" style="color: #1e40af;">
                                    Kebijakan TIK by Kemlu
                                </h2>
                                <p class="text-muted text-center flex-grow-1 small">
                                    Kebijakan teknologi informasi dan komunikasi yang dikeluarkan oleh Kementerian Luar Negeri.
                                </p>
                                <div class="text-center mt-2">
                                    <a href="{{ route('ktbk') }}" class="btn btn-outline-primary btn-sm">
                                        Lihat detail →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kebijakan TIK by Non Kemlu Section -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border-0 shadow-sm hover-card" style="background-color: #dcfce7;">
                            <div class="card-body d-flex flex-column p-3">
                                <div class="text-center mb-2">
                                    <i class="fas fa-users fa-2x mb-2" style="color: #166534;"></i>
                                </div>
                                <h2 class="h6 fw-semibold mb-2 text-center" style="color: #166534;">
                                    Kebijakan TIK by Non Kemlu
                                </h2>
                                <p class="text-muted text-center flex-grow-1 small">
                                    Kebijakan teknologi informasi dan komunikasi yang dikeluarkan oleh instansi selain Kemlu.
                                </p>
                                <div class="text-center mt-2">
                                    <a href="{{ route('ktbnk') }}" class="btn btn-outline-success btn-sm">
                                        Lihat detail →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nota Kesepahaman (MoU) dan PKS Section -->
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border-0 shadow-sm hover-card" style="background-color: #f3e8ff;">
                            <div class="card-body d-flex flex-column p-3">
                                <div class="text-center mb-2">
                                    <i class="fas fa-handshake fa-2x mb-2" style="color: #7c3aed;"></i>
                                </div>
                                <h2 class="h6 fw-semibold mb-2 text-center" style="color: #7c3aed;">
                                    Nota Kesepahaman (MoU) dan PKS
                                </h2>
                                <p class="text-muted text-center flex-grow-1 small">
                                    Dokumen kerja sama dan nota kesepahaman dengan berbagai instansi.
                                </p>
                                <div class="text-center mt-2">
                                    <a href="{{ route('nkmdp') }}" class="btn btn-sm" 
                                       style="border-color: #7c3aed; color: #7c3aed; background-color: transparent;">
                                        Lihat detail →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional CSS for enhanced styling -->
    <style>
        .text-shadow {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .hover-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        
        .hover-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        
        .min-vh-75 {
            min-height: 75vh;
        }
        
        @media (max-width: 768px) {
            .display-4 {
                font-size: 2rem;
            }
            
            .lead {
                font-size: 1rem;
            }
            
            .min-vh-75 {
                min-height: auto;
                padding-top: 2rem !important;
                padding-bottom: 2rem !important;
            }
        }
    </style>
</x-layout>