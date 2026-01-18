<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$stmt = $pdo->prepare("SELECT * FROM bon WHERE status='active' AND archived=0 ORDER BY id ASC");
$stmt->execute();
$bons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bonDetails = [];
$bonTotals = [];
foreach ($bons as $bon) {
    $bonDetails[$bon['id']] = getBonDetails($bon['id']);
    $totals = calculateBonTotals($bon['id']);
    $bonTotals[$bon['id']] = [
        'total_amount' => isset($totals['total_amount']) ? floatval($totals['total_amount']) : 0
    ]; 
}

$menusByCategory = [];
$stmtMenus = $pdo->prepare("SELECT * FROM menu ORDER BY category ASC, name ASC");
$stmtMenus->execute();
$allMenus = $stmtMenus->fetchAll(PDO::FETCH_ASSOC);
foreach ($allMenus as $menu) {
    $category = $menu['category'] ?? 'Uncategorized';
    $menusByCategory[$category][] = $menu;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sambel Uleg - Restoran Makanan Tradisional Autentik</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#ff6b35',
                        secondary: '#764ba2',
                        dark: '#2d260aff',
                        light: '#fff8f0',
                    },
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                    },
                    backgroundImage: {
                        'gradient-primary': 'linear-gradient(135deg, #ff6b35 0%, #f7931e 100%)',
                        'gradient-secondary': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.8s ease-out',
                        'fade-in-left': 'fadeInLeft 0.8s ease-out',
                        'fade-in-right': 'fadeInRight 0.8s ease-out',
                        'fade-in-down': 'fadeInDown 0.8s ease-out',
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeInLeft: {
                            '0%': { opacity: '0', transform: 'translateX(-30px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' },
                        },
                        fadeInRight: {
                            '0%': { opacity: '0', transform: 'translateX(30px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' },
                        },
                        fadeInDown: {
                            '0%': { opacity: '0', transform: 'translateY(-30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        html, body {
  height: 100%;
  margin: 0;
}
        body {
            font-family: 'Poppins', sans-serif;
            scroll-behavior: smooth;
        }
        
        /* Custom animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fadein {
            animation: fadeIn 1s ease forwards;
        }
        
        /* Hero section overlay */
        .hero-overlay {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7));
        }
        
        /* Custom gradient for navbar on scroll */
        .navbar-scrolled {
            background: rgba(213, 216, 18, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        /* Menu item hover effect */
        .menu-item {
            transition: all 0.3s ease;
        }
        
        .menu-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        /* Gallery item hover effect */
        .gallery-item {
            transition: all 0.4s ease;
        }
        
        .gallery-item:hover {
            transform: translateY(-8px);
        }
        
        /* Back to top button */
        .back-to-top {
            transition: all 0.4s ease;
        }
        
        @keyframes marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); } 
        }
        
        .animate-marquee {
            display: flex;
            width: max-content;
            animation: marquee 20s linear infinite; 
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out;
        }
        
        .nav-link {
            @apply text-white px-4 py-2 rounded-full hover:bg-white hover:bg-opacity-10 transition;
        }

        .mobile-link {
            @apply block text-white py-2 px-4 rounded-lg hover:bg-primary hover:bg-opacity-90 transition;
        }

        /* Animasi zoom background */
        @keyframes zoomslow {
            0% {
                transform: scale(1.05);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1.05);
            }
        }

        .animate-zoomslow {
            animation: zoomslow 20s ease-in-out infinite;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, #ff6b35, #f7931e);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, #e55a2b, #e0841a);
        }
        
        /* Loading animation */
        .loading-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(to right, #ff6b35, #f7931e);
            z-index: 9999;
            width: 0%;
            transition: width 0.4s ease;
        }
    </style>
</head>
<body class="font-poppins text-gray-800 bg-light">

  <!-- Loading Overlay -->
  <div id="loading" class="fixed inset-0 bg-white flex items-center justify-center z-50 transition-opacity duration-500">
      <div class="text-center">
          <i class="fas fa-pepper-hot text-6xl text-red-600 animate-bounce mb-4"></i>
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-t-4 border-blue-500 border-t-yellow-400 mb-4"></div>
          <p class="text-xl font-bold text-gray-800">SAMBEL ULEG</p>
          <p class="mt-2 text-gray-700 animate-pulse">Memuat halaman...</p>
      </div>
  </div>    

<script>
  window.addEventListener('load', function() {
      const loading = document.getElementById('loading');
      const content = document.getElementById('content');

      setTimeout(() => {
          loading.classList.add('opacity-0');
          setTimeout(() => loading.style.display = 'none', 500);

          content.classList.remove('opacity-0');
          content.classList.add('opacity-100');
      }, 500);
  });
</script>


    <!-- Navbar -->
    <nav id="navbar" class="fixed w-full z-50 transition-all duration-500 py-5 bg-transparent">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <a href="#home" class="flex items-center text-white text-2xl font-bold animate-fade-in-left">
                    <img src="img/logo.jpg" alt="Logo Sambel Uleg" class="h-10 w-10 mr-2 rounded-full shadow-md object-cover">
                    Sambel Uleg
                </a>

                <!-- Mobile menu button -->
                <button class="lg:hidden text-white focus:outline-none z-50 animate-fade-in-right" id="mobile-menu-button">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>

                <!-- Desktop menu -->
                <div class="hidden lg:flex space-x-4 animate-fade-in-down">
                    <a href="#home" class="nav-link">Home</a>
                    <a href="#about" class="nav-link">Tentang Kami</a>
                    <a href="#menu" class="nav-link">Menu</a>
                    <a href="#gallery" class="nav-link">Galeri</a>
                    <a href="#location" class="nav-link">Lokasi</a>
                    <a href="#" class="nav-link" id="login-button">
                        <i class="fas fa-sign-in-alt mr-1"></i>Login
                    </a>
                </div>
            </div>

            <!-- Mobile menu -->
            <div class="lg:hidden hidden mt-4 bg-black bg-opacity-90 rounded-xl p-4 transform origin-top scale-y-0 transition-transform duration-300 ease-in-out" id="mobile-menu">
                <a href="#home" class="mobile-link animate-fade-in-right" style="animation-delay: 0.1s">Home</a>
                <a href="#about" class="mobile-link animate-fade-in-right" style="animation-delay: 0.2s">Tentang Kami</a>
                <a href="#menu" class="mobile-link animate-fade-in-right" style="animation-delay: 0.3s">Menu</a>
                <a href="#gallery" class="mobile-link animate-fade-in-right" style="animation-delay: 0.4s">Galeri</a>
                <a href="#location" class="mobile-link animate-fade-in-right" style="animation-delay: 0.5s">Lokasi</a>
                <a href="#" class="mobile-link animate-fade-in-right" style="animation-delay: 0.6s" id="mobile-login-button">
                    <i class="fas fa-sign-in-alt mr-1"></i>Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="min-h-screen flex items-center justify-center relative overflow-hidden">
        <!-- Background -->
        <div class="absolute inset-0 w-full h-full">
            <img src="img/gambar1.jpg" alt="background" class="w-full h-full object-cover transform scale-105 animate-zoomslow">
            <div class="absolute inset-0 bg-gradient-to-b from-black/60 to-black/40"></div>
        </div>

        <!-- Content -->
        <div class="container mx-auto px-4 md:px-6 z-10 text-center text-white" data-aos="fade-up">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold mb-4 tracking-wide drop-shadow-lg animate-fade-in-down">
                Sambel Uleg Kebagusan
            </h1>
            <p class="text-lg md:text-2xl mb-8 opacity-90 animate-fade-in-up" style="animation-delay: 0.3s">
                Pilihan Keluarga & Teman Sejak 2006
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4 animate-fade-in-up" style="animation-delay: 0.6s">
                <a href="#menu" class="bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white font-semibold py-3 px-8 rounded-full transition-transform duration-300 transform hover:-translate-y-1 hover:shadow-xl animate-pulse-slow">
                    Lihat Menu
                </a>
                <a href="#location" class="bg-transparent border-2 border-white hover:bg-white hover:text-orange-500 text-white font-semibold py-3 px-8 rounded-full transition-all duration-300 transform hover:-translate-y-1">
                    Kunjungi Kami
                </a>
            </div>
        </div>
        
        <!-- Scroll indicator -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
            <div class="w-6 h-10 border-2 border-white rounded-full flex justify-center">
                <div class="w-1 h-3 bg-white rounded-full mt-2 animate-pulse"></div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-16 md:py-24 bg-light overflow-hidden">
        <div class="container mx-auto px-4 md:px-6">
            <!-- Judul -->
            <h2 class="text-3xl md:text-4xl font-bold text-center mb-16 relative after:content-[''] after:absolute after:bottom-[-15px] after:left-1/2 after:transform after:-translate-x-1/2 after:w-20 after:h-1 after:bg-gradient-to-r after:from-primary after:to-secondary animate-fade-in-down">
                Tentang Kami
            </h2>

            <!-- Bagian 1 -->
            <div class="flex flex-col lg:flex-row items-center gap-10 mb-20">
                <!-- Gambar Landscape -->
                <div class="w-full lg:w-1/2 animate-fade-in-left">
                    <div class="rounded-2xl overflow-hidden shadow-xl">
                        <img src="img/gambar1.jpg" alt="Tentang Sambel Uleg" class="w-full h-64 md:h-96 object-cover object-center transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
                <!-- Text -->
                <div class="w-full lg:w-1/2 animate-fade-in-right">
                    <h3 class="text-2xl md:text-3xl font-bold mb-6">Sambel Uleg Sejak 2006</h3>
                    <p class="text-lg mb-4">
                        “Pertama dibuka dengan hanya 1 cabang di Kebaagusan pintu timur dam berkambang ke daerah lainnya .
                    </p>
                    <p class="mb-8">
                        Varian lauknya beragam—dari ayam kampung goreng, lele, bebek, ikan asin, hingga sayur asam dan lalapan.
                        Rasa sambelnya autentik, pedasnya pas, cocok untuk sharing bersama keluarga atau teman.”
                    </p>
                    <div class="bg-orange-100 border-l-4 border-orange-500 text-orange-700 p-4 rounded-r-lg">
                        <p class="flex items-center">
                            <i class="fas fa-quote-left text-orange-500 mr-2"></i>
                            <span>Rasa yang mengingatkan pada masakan rumah nenek</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bagian 2 -->
            <div class="flex flex-col lg:flex-row-reverse items-center gap-10 mb-20">
                <!-- Gambar Landscape -->
                <div class="w-full lg:w-1/2 animate-fade-in-right">
                    <div class="rounded-2xl overflow-hidden shadow-xl">
                        <img src="img/gambar2.jpg" alt="Rasa Autentik Nusantara" class="w-full h-64 md:h-96 object-cover object-center transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
                <!-- Text -->
                <div class="w-full lg:w-1/2 animate-fade-in-left">
                    <h3 class="text-2xl md:text-3xl font-bold mb-6">Rasa Autentik Nusantara</h3>
                    <p class="text-lg mb-4">
                        Dari bahan pilihan hingga cara memasak tradisional, kami menjaga keaslian rasa khas Indonesia.
                    </p>
                    <p class="mb-8">
                        Kombinasi bumbu khas dan sambel dadakan menjadikan setiap hidangan terasa unik dan menggugah selera.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Bumbu tradisional pilihan</li>
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Rempah-rempah segar</li>
                        <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Proses memasak alami</li>
                    </ul>
                </div>
            </div>

            <!-- Bagian 3 -->
            <div class="flex flex-col lg:flex-row items-center gap-10">
                <!-- Gambar Landscape -->
                <div class="w-full lg:w-1/2 animate-fade-in-left">
                    <div class="rounded-2xl overflow-hidden shadow-xl">
                        <img src="img/gambar3.jpg" alt="Suasana Hangat & Nyaman" class="w-full h-64 md:h-96 object-cover object-center transition-transform duration-700 hover:scale-105">
                    </div>
                </div>
                <!-- Text -->
                <div class="w-full lg:w-1/2 animate-fade-in-right">
                    <h3 class="text-2xl md:text-3xl font-bold mb-6">Suasana Hangat & Nyaman</h3>
                    <p class="text-lg mb-4">
                        Bukan hanya soal makanan, tetapi juga pengalaman makan bersama dalam suasana yang penuh kehangatan.
                    </p>
                    <p class="mb-8">
                        Dengan tempat lesehan, dekorasi sederhana, dan pelayanan ramah, setiap kunjungan terasa seperti pulang ke rumah sendiri.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-lg shadow-md text-center">
                            <i class="fas fa-users text-3xl text-orange-500 mb-2"></i>
                            <p class="font-semibold">Ramah Keluarga</p>
                        </div>
                        <div class="bg-white p-4 rounded-lg shadow-md text-center">
                            <i class="fas fa-couch text-3xl text-orange-500 mb-2"></i>
                            <p class="font-semibold">Tempat Lesehan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Ulasan Section -->
    <section id="reviews" class="py-16 md:py-24 bg-gray-50 overflow-hidden">
        <div class="container mx-auto px-4 md:px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-12 relative after:content-[''] after:absolute after:bottom-[-15px] after:left-1/2 after:transform after:-translate-x-1/2 after:w-20 after:h-1 after:bg-gradient-to-r after:from-yellow-400 after:to-red-500 animate-fade-in-down">
                Apa Kata Mereka
            </h2>

            <!-- Wrapper -->
            <div class="overflow-hidden relative">
                <!-- Sliding track -->
                <div class="flex animate-marquee space-x-6">
                    <!-- SET 1 -->
                    <div class="min-w-[350px] sm:min-w-[400px] md:min-w-[500px] rounded-xl shadow-lg overflow-hidden bg-white animate-float" style="animation-delay: 0s">
                        <img src="img/ulasan1.jpg" alt="Ulasan 1" class="w-full h-72 object-cover">
                    </div>
                    <div class="min-w-[350px] sm:min-w-[400px] md:min-w-[500px] rounded-xl shadow-lg overflow-hidden bg-white animate-float" style="animation-delay: 0.5s">
                        <img src="img/ulasan2.jpg" alt="Ulasan 2" class="w-full h-72 object-cover">
                    </div>
                    <div class="min-w-[350px] sm:min-w-[400px] md:min-w-[500px] rounded-xl shadow-lg overflow-hidden bg-white animate-float" style="animation-delay: 1s">
                        <img src="img/ulasan3.jpg" alt="Ulasan 3" class="w-full h-72 object-cover">
                    </div>
                    <div class="min-w-[350px] sm:min-w-[400px] md:min-w-[500px] rounded-xl shadow-lg overflow-hidden bg-white animate-float" style="animation-delay: 1.5s">
                        <img src="img/galeri2.jpg" alt="Ulasan 4" class="w-full h-72 object-cover">
                    </div>

                    <!-- SET 2 (duplikasi biar seamless) -->
                    <div class="min-w-[350px] sm:min-w-[400px] md:min-w-[500px] rounded-xl shadow-lg overflow-hidden bg-white animate-float" style="animation-delay: 0s">
                        <img src="img/ulasan1.jpg" alt="Ulasan 1" class="w-full h-72 object-cover">
                    </div>
                    <div class="min-w-[350px] sm:min-w-[400px] md:min-w-[500px] rounded-xl shadow-lg overflow-hidden bg-white animate-float" style="animation-delay: 0.5s">
                        <img src="img/ulasan2.jpg" alt="Ulasan 2" class="w-full h-72 object-cover">
                    </div>
                    <div class="min-w-[350px] sm:min-w-[400px] md:min-w-[500px] rounded-xl shadow-lg overflow-hidden bg-white animate-float" style="animation-delay: 1s">
                        <img src="img/ulasan3.jpg" alt="Ulasan 3" class="w-full h-72 object-cover">
                    </div>
                    <div class="min-w-[350px] sm:min-w-[400px] md:min-w-[500px] rounded-xl shadow-lg overflow-hidden bg-white animate-float" style="animation-delay: 1.5s">
                        <img src="img/galeri2.jpg" alt="Ulasan 4" class="w-full h-72 object-cover">
                    </div>
                </div>
            </div>
            
            <!-- Review stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-16">
                <div class="bg-white p-6 rounded-xl shadow-md animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="text-3xl md:text-4xl font-bold text-orange-500 mb-2">4.8</div>
                    <div class="flex justify-center mb-2">
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star text-yellow-400"></i>
                        <i class="fas fa-star-half-alt text-yellow-400"></i>
                    </div>
                    <p class="text-gray-600">Rating Google</p>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-md animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="text-3xl md:text-4xl font-bold text-orange-500 mb-2">17+</div>
                    <p class="text-gray-600">Tahun</p>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-md animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="text-3xl md:text-4xl font-bold text-orange-500 mb-2">500+</div>
                    <p class="text-gray-600">Pelanggan/Hari</p>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-md animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="text-3xl md:text-4xl font-bold text-orange-500 mb-2">50+</div>
                    <p class="text-gray-600">Menu Variatif</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section id="menu" class="py-16 md:py-24 bg-cover bg-fixed bg-center relative overflow-hidden" style="background-image:url('img/gambar2.jpg');">
        <div class="absolute inset-0 bg-light bg-opacity-95"></div>
        <div class="container mx-auto px-4 md:px-6 relative z-10">
            <h2 class="text-3xl md:text-4xl font-bold text-center mb-16 relative after:content-[''] after:absolute after:bottom-[-15px] after:left-1/2 after:transform after:-translate-x-1/2 after:w-20 after:h-1 after:bg-gradient-primary animate-fade-in-down">
                Menu Andalan
            </h2>

            <!-- GRID: HP = 2 kolom, Laptop = 3 kolom -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <!-- Menu Item 1-->
                <div class="menu-item bg-white rounded-2xl overflow-hidden shadow-lg animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="h-40 md:h-56 overflow-hidden">
                        <img src="img/ayamkampung.jpeg" alt="Ayam Kampung Kebagusan" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                    </div>
                    <div class="p-3 md:p-6">
                        <h3 class="text-base md:text-xl font-bold mb-2">Ayam Kampung Kebagusan</h3>
                        <p class="text-gray-600 mb-3 md:mb-4 text-xs md:text-base">Ayam kampung dengan bumbu khas, disajikan dengan sambal uleg.</p>
                        <div class="flex justify-between items-center">
                            <span class="text-primary font-bold text-sm md:text-xl">Rp 20.000</span>
                            <button class="bg-orange-500 text-white px-3 py-1 rounded-full text-sm hover:bg-orange-600 transition">+</button>
                        </div>
                    </div>
                </div>

                <!-- Menu Item2 -->
                <div class="menu-item bg-white rounded-2xl overflow-hidden shadow-lg animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="h-40 md:h-56 overflow-hidden">
                        <img src="img/pepesmas.webp" alt="Pepes Ikan" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                    </div>
                    <div class="p-3 md:p-6">
                        <h3 class="text-base md:text-xl font-bold mb-2">Pepes Ikan</h3>
                        <p class="text-gray-600 mb-3 md:mb-4 text-xs md:text-base">Ikan yang dibumbui dan dibungkus daun pisang, kemudian dikukus.</p>
                        <div class="flex justify-between items-center">
                            <span class="text-primary font-bold text-sm md:text-xl">Rp 20.000</span>
                            <button class="bg-orange-500 text-white px-3 py-1 rounded-full text-sm hover:bg-orange-600 transition">+</button>
                        </div>
                    </div>
                </div>

                <!-- Menu Item3 -->
                <div class="menu-item bg-white rounded-2xl overflow-hidden shadow-lg animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="h-40 md:h-56 overflow-hidden">
                        <img src="img/pepestahu.webp" alt="Sayur Asem" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                    </div>
                    <div class="p-3 md:p-6">
                        <h3 class="text-base md:text-xl font-bold mb-2">Pepes Tahu</h3>
                        <p class="text-gray-600 mb-3 md:mb-4 text-xs md:text-base">Pepes tahu dengan bumbu rempah yang kaya rasa</p>
                        <div class="flex justify-between items-center">
                            <span class="text-primary font-bold text-sm md:text-xl">Rp 7.000</span>
                            <button class="bg-orange-500 text-white px-3 py-1 rounded-full text-sm hover:bg-orange-600 transition">+</button>
                        </div>
                    </div>
                </div>
                
                <!-- Menu Item4 -->
                <div class="menu-item bg-white rounded-2xl overflow-hidden shadow-lg animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="h-40 md:h-56 overflow-hidden">
                        <img src="img/jengkol.jpg" alt="Sayur Asem" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                    </div>
                    <div class="p-3 md:p-6">
                        <h3 class="text-base md:text-xl font-bold mb-2">Jengkol Balado</h3>
                        <p class="text-gray-600 mb-3 md:mb-4 text-xs md:text-base">Jengkol dengan bumbu balado pedas</p>
                        <div class="flex justify-between items-center">
                            <span class="text-primary font-bold text-sm md:text-xl">Rp 10.000</span>
                            <button class="bg-orange-500 text-white px-3 py-1 rounded-full text-sm hover:bg-orange-600 transition">+</button>
                        </div>
                    </div>
                </div>
                
                <!-- Menu 5 -->
                <div class="menu-item bg-white rounded-2xl overflow-hidden shadow-lg animate-fade-in-up" style="animation-delay: 0.5s">
                    <div class="h-40 md:h-56 overflow-hidden">
                        <img src="img/sayurasem.jpg" alt="Sayur Asem" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                    </div>
                    <div class="p-3 md:p-6">
                        <h3 class="text-base md:text-xl font-bold mb-2">Sayur Asem</h3>
                        <p class="text-gray-600 mb-3 md:mb-4 text-xs md:text-base">Sayur asem segar dengan bumbu rempah pilihan</p>
                        <div class="flex justify-between items-center">
                            <span class="text-primary font-bold text-sm md:text-xl">Rp 10.000</span>
                            <button class="bg-orange-500 text-white px-3 py-1 rounded-full text-sm hover:bg-orange-600 transition">+</button>
                        </div>
                    </div>
                </div>

                <!-- Menu Item 6-->
                <div class="menu-item bg-white rounded-2xl overflow-hidden shadow-lg animate-fade-in-up" style="animation-delay: 0.6s">
                    <div class="h-40 md:h-56 overflow-hidden">
                        <img src="img/bebek.jpg" alt="Bebek Goreng" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                    </div>
                    <div class="p-3 md:p-6">
                        <h3 class="text-base md:text-xl font-bold mb-2">Bebek Goreng</h3>
                        <p class="text-gray-600 mb-3 md:mb-4 text-xs md:text-base">Bebek dengan harga yang murah se Kebagusan</p>
                        <div class="flex justify-between items-center">
                            <span class="text-primary font-bold text-sm md:text-xl">Rp 20.000</span>
                            <button class="bg-orange-500 text-white px-3 py-1 rounded-full text-sm hover:bg-orange-600 transition">+</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Button -->
            <div class="text-center mt-12 animate-fade-in-up" style="animation-delay: 0.7s">
                    <a href="generate_qr.php" class="bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white font-semibold py-3 px-8 rounded-full transition-transform duration-300 transform hover:-translate-y-1 hover:shadow-xl shadow-lg flex items-center justify-center">
                        <i class="fas fa-qrcode mr-3 text-xl"></i>
                        <span class="text-lg">Lihat Menu</span>
                    </a>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery" class="py-16 md:py-24 bg-light overflow-hidden">
        <div class="container mx-auto px-4 md:px-6">
            <h2 class="text-3xl md:text-4xl font-bold text-center mb-16 relative after:content-[''] after:absolute after:bottom-[-15px] after:left-1/2 after:transform after:-translate-x-1/2 after:w-20 after:h-1 after:bg-gradient-primary animate-fade-in-down">
                Galeri Kami
            </h2>

            <!-- Grid Instagram style -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-4">
                <!-- Item 1 -->
                <div class="gallery-item overflow-hidden rounded-lg animate-fade-in-up" style="animation-delay: 0.1s">
                    <img src="img/galeri8.jpg" alt="Interior Restoran" class="w-full aspect-square object-cover transition-transform duration-500 hover:scale-110">
                </div>
                
                <!-- Item 2 -->
                <div class="gallery-item overflow-hidden rounded-lg animate-fade-in-up" style="animation-delay: 0.2s">
                    <img src="img/galeri7.jpg" alt="Interior Restoran" class="w-full aspect-square object-cover transition-transform duration-500 hover:scale-110">
                </div>

                <!-- Item 3 -->
                <div class="gallery-item overflow-hidden rounded-lg animate-fade-in-up" style="animation-delay: 0.3s">
                    <img src="img/galeri4.jpg" alt="Hidangan Spesial" class="w-full aspect-square object-cover transition-transform duration-500 hover:scale-110">
                </div>

                <!-- Item 4 -->
                <div class="gallery-item overflow-hidden rounded-lg animate-fade-in-up" style="animation-delay: 0.4s">
                    <img src="img/galeri3.jpg" alt="Proses Memasak" class="w-full aspect-square object-cover transition-transform duration-500 hover:scale-110">
                </div>

                <!-- Item 5 -->
                <div class="gallery-item overflow-hidden rounded-lg animate-fade-in-up" style="animation-delay: 0.5s">
                    <img src="img/galeri9.jpg" alt="Acara Khusus" class="w-full aspect-square object-cover transition-transform duration-500 hover:scale-110">
                </div>

                <!-- Item 6 -->
                <div class="gallery-item overflow-hidden rounded-lg animate-fade-in-up" style="animation-delay: 0.6s">
                    <img src="img/galeri6.jpg" alt="Suasana Restoran" class="w-full aspect-square object-cover transition-transform duration-500 hover:scale-110">
                </div>

                <!-- Item 7 -->
                <div class="gallery-item overflow-hidden rounded-lg animate-fade-in-up" style="animation-delay: 0.7s">
                    <img src="img/galeri1.jpg" alt="Pelanggan" class="w-full aspect-square object-cover transition-transform duration-500 hover:scale-110">
                </div>

                <!-- Item 8 -->
                <div class="gallery-item overflow-hidden rounded-lg animate-fade-in-up" style="animation-delay: 0.8s">
                    <img src="img/galeri2.jpg" alt="Makanan" class="w-full aspect-square object-cover transition-transform duration-500 hover:scale-110">
                </div>
            </div>

            <!-- Instagram link -->
            <div class="text-center mt-12 animate-fade-in-up" style="animation-delay: 0.9s">
                <a href="#" class="inline-flex items-center bg-gradient-to-r from-pink-500 via-red-500 to-yellow-500 text-white font-semibold py-3 px-6 rounded-full transition-all duration-300 transform hover:-translate-y-1 shadow-lg hover:shadow-xl">
                    <i class="fab fa-instagram text-xl mr-2"></i> Ikuti Kami di Instagram
                </a>
            </div>
        </div>
    </section>

    <!-- Location Section -->
    <section id="location" class="py-16 md:py-24 bg-gray-50 overflow-hidden">
        <div class="container mx-auto px-4 md:px-6">
            <h2 class="text-3xl md:text-4xl font-bold text-center mb-16 relative after:content-[''] after:absolute after:bottom-[-15px] after:left-1/2 after:transform after:-translate-x-1/2 after:w-20 after:h-1 after:bg-gradient-primary animate-fade-in-down">
                Lokasi Kami
            </h2>

            <div class="flex flex-col lg:flex-row gap-10">
                <!-- Map -->
                <div class="w-full lg:w-1/2 animate-fade-in-left">
                    <div class="mapouter mt-8">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.521142032811!2d106.82367381529182!3d-6.311688385505871!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69ed001f1560e7%3A0x462dd7670d89abf8!2sJl.%20Kebagusan%20Raya%20No.12A%2C%20RT.8%2FRW.6%2C%20Kebagusan%2C%20Ps.%20Minggu%2C%20Jakarta%20Selatan!5e0!3m2!1sid!2sid!4v1695478999999!5m2!1sid!2sid"
                        width="100%"
                        height="400"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                    </div>

                </div>

                <!-- Info -->
                <div class="w-full lg:w-1/2 animate-fade-in-right">
                    <h3 class="text-2xl md:text-3xl font-bold mb-6">Kunjungi Warung Kami</h3>
                    <p class="mb-6">Kami berada di lokasi yang strategis dan mudah dijangkau. Datang dan nikmati pengalaman kuliner yang tak terlupakan!</p>
                    
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="bg-primary p-3 rounded-full mr-4">
                                <i class="fas fa-map-marker-alt text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Alamat</h4>
                                <p class="text-gray-600">Jl. Kebagusan Raya No. 123, Jakarta Selatan</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="bg-primary p-3 rounded-full mr-4">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Jam Operasional</h4>
                                <p class="text-gray-600">Setiap Hari: 09.00 - 21.00 WIB</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="bg-primary p-3 rounded-full mr-4">
                                <i class="fas fa-phone text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Telepon</h4>
                                <p class="text-gray-600">+62 21 765 4321</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="bg-primary p-3 rounded-full mr-4">
                                <i class="fab fa-whatsapp text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">WhatsApp</h4>
                                <p class="text-gray-600">+62 812 3456 7890</p>
                            </div>
                        </div>
                    </div>
                <div class="mt-8">
                    <a href="https://www.google.com/maps?q=-6.3116884,106.825862" target="_blank" class="inline-flex items-center bg-gradient-primary text-white font-semibold py-3 px-6 rounded-full transition-all duration-300 transform hover:-translate-y-1 shadow-lg hover:shadow-xl mr-4">
                        <i class="fas fa-map-marker-alt mr-2"></i> Buka di Google Maps
                    </a>
                    <a href="https://wa.me/6281234567890" target="_blank" class="inline-flex items-center bg-green-500 text-white font-semibold py-3 px-6 rounded-full transition-all duration-300 transform hover:-translate-y-1 shadow-lg hover:shadow-xl mt-4 md:mt-0">
                        <i class="fab fa-whatsapp mr-2"></i> Hubungi via WhatsApp
                    </a>
                </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white pt-16 pb-8 overflow-hidden">
        <div class="container mx-auto px-4 md:px-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                <!-- About -->
                <div class="animate-fade-in-up" style="animation-delay: 0.1s">
                    <h3 class="text-xl font-bold mb-6 flex items-center">
                        <img src="img/logo.jpg" alt="Logo Sambel Uleg" class="h-8 w-8 mr-2 rounded-full shadow-md object-cover">
                        Sambel Uleg
                    </h3>
                    <p class="mb-4">Menyajikan makanan tradisional autentik dengan cita rasa yang mengingatkan pada masakan rumah sejak 2006.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-white hover:text-primary transition"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white hover:text-primary transition"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white hover:text-primary transition"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white hover:text-primary transition"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <!-- Links -->
                <div class="animate-fade-in-up" style="animation-delay: 0.2s">
                    <h3 class="text-xl font-bold mb-6">Tautan Cepat</h3>
                    <ul class="space-y-2">
                        <li><a href="#home" class="hover:text-primary transition">Home</a></li>
                        <li><a href="#about" class="hover:text-primary transition">Tentang Kami</a></li>
                        <li><a href="#menu" class="hover:text-primary transition">Menu</a></li>
                        <li><a href="#gallery" class="hover:text-primary transition">Galeri</a></li>
                        <li><a href="#location" class="hover:text-primary transition">Lokasi</a></li>
                    </ul>
                </div>
                
                <!-- Contact -->
                <div class="animate-fade-in-up" style="animation-delay: 0.3s">
                    <h3 class="text-xl font-bold mb-6">Kontak Kami</h3>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-3 text-primary"></i>
                            <span>Jl. Kebagusan Raya No. 123, Jakarta Selatan</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-phone mt-1 mr-3 text-primary"></i>
                            <span>+62 21 765 4321</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-envelope mt-1 mr-3 text-primary"></i>
                            <span>info@sambeluleg.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 pt-8 text-center animate-fade-in-up" style="animation-delay: 0.5s">
                <p>&copy; 2023 Sambel Uleg Kebagusan. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Back to top button -->
    <button id="back-to-top" class="fixed bottom-8 right-8 bg-gradient-primary text-white p-3 rounded-full shadow-lg opacity-0 transition-all duration-300 back-to-top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-out-quad',
            once: true,
            offset: 50
        });

        // Loading bar simulation
        window.addEventListener('load', function() {
            const loadingBar = document.getElementById('loading-bar');
            loadingBar.style.width = '100%';
            setTimeout(() => {
                loadingBar.style.opacity = '0';
            }, 800);
        });

        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
            mobileMenu.classList.toggle('scale-y-0');
            mobileMenu.classList.toggle('scale-y-100');
        });

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            const backToTop = document.getElementById('back-to-top');
            
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-scrolled');
                navbar.classList.remove('py-5');
                navbar.classList.add('py-3');
                backToTop.classList.add('opacity-100');
            } else {
                navbar.classList.remove('navbar-scrolled');
                navbar.classList.remove('py-3');
                navbar.classList.add('py-5');
                backToTop.classList.remove('opacity-100');
            }
        });

        // Back to top button
        document.getElementById('back-to-top').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    // Close mobile menu if open
                    const mobileMenu = document.getElementById('mobile-menu');
                    mobileMenu.classList.add('hidden');
                    mobileMenu.classList.remove('scale-y-100');
                    mobileMenu.classList.add('scale-y-0');
                    
                    // Scroll to element
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
  function openLoginModal() {
    document.getElementById('login-modal').classList.remove('hidden');
  }
  function closeLoginModal() {
    document.getElementById('login-modal').classList.add('hidden');
  }

  // Ganti alert login dengan modal
  document.getElementById('login-button').addEventListener('click', function(e) {
    e.preventDefault();
    openLoginModal();
  });
  document.getElementById('mobile-login-button').addEventListener('click', function(e) {
    e.preventDefault();
    openLoginModal();
  });

  let currentUUID = '';

function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c == 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function generateQRCode() {
    currentUUID = generateUUID();
    const url = `${window.location.origin}/pesan/${currentUUID}`;
    
    // Clear previous QR code
    document.getElementById('qrcode-container').innerHTML = '';
    
    // Generate new QR code
    QRCode.toCanvas(document.getElementById('qrcode-container'), url, {
        width: 200,
        height: 200,
        margin: 1
    }, function(error) {
        if (error) console.error(error);
    });
}

function generateNewQR() {
    generateQRCode();
}

// Generate QR code on page load
document.addEventListener('DOMContentLoaded', generateQRCode);
    </script>
    <!-- Modal Login -->
<div id="login-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
    <h2 class="text-xl font-bold mb-4">Login</h2>
    <form action="login.php" method="POST">
      <div class="mb-4">
        <label class="block text-gray-700">Username</label>
        <input type="text" name="username" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
      </div>
      <div class="mb-4">
        <label class="block text-gray-700">Password</label>
        <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
      </div>
      <div class="flex justify-between items-center">
        <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600">Login</button>
        <button type="button" onclick="closeLoginModal()" class="text-gray-500 hover:text-gray-700">Tutup</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>