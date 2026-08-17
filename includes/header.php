<?php
if (!defined('SITE_NAME')) {
    include_once(__DIR__ . "/../config.php");
}

$page_title = isset($page_title) ? $page_title . " | " . SITE_NAME : SITE_NAME . " - " . SITE_TAGLINE;
$is_in_subfolder = (strpos($_SERVER['SCRIPT_NAME'], '/user/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$root = $is_in_subfolder ? "../" : "./";
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                            950: '#1e1b4b',
                        },
                        accent: {
                            500: '#ec4899',
                            600: '#db2777',
                            700: '#be185d',
                        }
                    },
                    boxShadow: {
                        'glow-brand': '0 0 25px -5px rgba(99, 102, 241, 0.4)',
                        'glow-accent': '0 0 25px -5px rgba(236, 72, 153, 0.4)',
                        'glow-emerald': '0 0 25px -5px rgba(16, 185, 129, 0.4)',
                    },
                    animation: {
                        'laser': 'laser 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        laser: {
                            '0%, 100%': { transform: 'translateY(0%)', opacity: '0.9' },
                            '50%': { transform: 'translateY(190px)', opacity: '0.4' },
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Biometric Face Engine -->
    <script src="<?php echo $root; ?>assets/js/biometric.js?v=<?php echo time(); ?>"></script>

    <style>
        /* Smooth Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .font-heading { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="flex flex-col min-h-full bg-slate-50 text-slate-800 antialiased font-sans">