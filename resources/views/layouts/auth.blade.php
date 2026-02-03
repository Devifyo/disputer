<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal') - BureaucracyResolver</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        'primary-hover': '#1d4ed8',
                        'subtle': '#f3f4f6'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-white h-screen overflow-hidden">

    <div class="w-full h-full flex">

        <div class="hidden lg:flex w-5/12 bg-slate-900 relative flex-col justify-between p-12 text-white">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-900 to-slate-900 opacity-90 z-0"></div>
            <div class="absolute inset-0 z-0" style="background-image: radial-gradient(#3b82f6 1px, transparent 1px); background-size: 32px 32px; opacity: 0.1;"></div>

            <div class="relative z-10 font-bold text-2xl tracking-tight flex items-center gap-2">
                <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center border border-white/20 backdrop-blur-sm">
                    <svg class="w-6 h-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                </div>
                DisputeResolution
            </div>

            <div class="relative z-10 mb-10">
                <h2 class="text-3xl font-semibold mb-4">Secure Case Management</h2>
                <p class="text-blue-200 font-light text-lg">
                    Manage procedures, track escalations, and resolve complex bureaucracy issues efficiently.
                </p>
            </div>

            <div class="relative z-10 text-xs text-gray-500">
                &copy; {{ date('Y') }} Resolution Systems Inc.
            </div>
        </div>

        <div class="w-full lg:w-7/12 flex flex-col bg-white h-full overflow-y-auto">

            <div class="flex-grow flex items-center justify-center p-6 sm:p-12">
                <div class="w-full max-w-sm space-y-8 fade-in">
                    @yield('content')
                </div>
            </div>

            <footer class="py-6 border-t border-gray-100 bg-gray-50 lg:bg-white lg:border-none">
                <div class="max-w-7xl mx-auto px-6 flex flex-wrap justify-center gap-x-8 gap-y-2">
                    <a href="#" class="text-xs text-gray-500 hover:text-primary transition-colors">Privacy Policy</a>
                    <a href="#" class="text-xs text-gray-500 hover:text-primary transition-colors">Terms & Conditions</a>
                    <a href="#" class="text-xs text-gray-500 hover:text-primary transition-colors">About Us</a>
                    <a href="#" class="text-xs text-gray-500 hover:text-primary transition-colors">Contact Support</a>
                </div>
            </footer>

        </div>
    </div>

    @stack('scripts')
</body>
</html>
