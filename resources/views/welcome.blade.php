<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OrganizApp</title>
        <!-- Fonts -->
        <linkpreconnect href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
        <!-- Tailwind CSS (CDN para prototipado rápido) -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="antialiased bg-gray-50 text-gray-800">
        <div class="relative min-h-screen flex flex-col justify-center items-center selection:bg-red-500 selection:text-white">
            
            <!-- Navbar Simple -->
            <nav class="absolute top-0 w-full p-6 flex justify-between items-center max-w-7xl mx-auto">
                <div class="text-2xl font-bold text-red-600">OrganizApp</div>
                <div>
                    @if (Route::has('login'))
                        <div class="space-x-4">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="font-semibold text-gray-600 hover:text-gray-900">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="font-semibold text-gray-600 hover:text-gray-900">Log in</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="font-semibold text-gray-600 hover:text-gray-900">Register</a>
                                @endif
                            @endauth
                        </div>
                    @endif
                </div>
            </nav>

            <!-- Hero Section -->
            <div class="max-w-7xl mx-auto p-6 lg:p-8 text-center">
                <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight text-gray-900 mb-6">
                    Organiza tu vida con <span class="text-red-600">OrganizApp</span>
                </h1>
                
                <p class="mt-4 text-xl text-gray-600 max-w-2xl mx-auto">
                    La plataforma integral para gestionar tus tareas, proyectos y metas personales. 
                    Estructura tu día a día con nuestros módulos especializados.
                </p>

                <div class="mt-10 flex justify-center gap-4">
                    <a href="#" class="px-8 py-3 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition duration-300">
                        Comenzar Ahora
                    </a>
                    <a href="#modulos" class="px-8 py-3 bg-white text-red-600 font-semibold rounded-lg shadow-md border border-gray-200 hover:bg-gray-50 transition duration-300">
                        Ver Módulos
                    </a>
                </div>
            </div>

            <!-- Módulos Preview -->
            <div id="modulos" class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-8 max-w-7xl mx-auto px-6">
                <!-- Módulo 1 -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4 text-red-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Calendario Inteligente</h3>
                    <p class="mt-2 text-gray-500">Planifica tus eventos y recordatorios con una vista clara de tu tiempo.</p>
                </div>

                <!-- Módulo 2 -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4 text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.25 2.25 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Gestor de Tareas</h3>
                    <p class="mt-2 text-gray-500">Listas To-Do avanzadas con prioridades y seguimiento de progreso.</p>
                </div>

                <!-- Módulo 3 -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4 text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Finanzas Personales</h3>
                    <p class="mt-2 text-gray-500">Controla tus gastos e ingresos de forma sencilla y visual.</p>
                </div>
            </div>

            <footer class="mt-20 py-6 text-center text-gray-500 text-sm">
                &copy; {{ date('Y') }} OrganizApp. Todos los derechos reservados.
            </footer>
        </div>
    </body>
</html>