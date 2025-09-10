{{-- resources/views/components/layout.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <title>Beranda</title>
    <style>
        .homepage-background {
            background-image: url('{{ asset('images/background-image.jpeg') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        
        .homepage-overlay {
            background: linear-gradient(to bottom, rgba(30,64,175,0.8) 0%, rgba(30,64,175,0.4) 40%, rgba(0,0,0,0.1) 70%, rgba(0,0,0,0) 100%);
            min-height: 100vh;
        }
    </style>
</head>

<body class="h-100 homepage-background min-vh-100">

<div class="d-flex flex-column min-vh-100 homepage-overlay">
   <x-navbar></x-navbar>
  
   <x-header>{{$title}}</x-header>

    <main class="flex-grow-1 d-flex align-items-center fade-in">
      <div class="container-fluid">
        <!-- Your content -->
       {{ $slot }}
      </div>
    </main>

    <!-- Document Quick View Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalLabel">Pratinjau Dokumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="documentModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memuat dokumen...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <a href="#" id="viewFullDocument" class="btn btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt me-1"></i>
                        Lihat Lengkap
                    </a>
                </div>
            </div>
        </div>
    </div>

    
  
</body>
</html>
