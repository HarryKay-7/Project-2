<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .fade-in-greeting {
            opacity: 0;
            transform: translateY(-20px);
            animation: fadeInGreeting 1.2s ease-out forwards;
        }
        @keyframes fadeInGreeting {
            0% { opacity: 0; transform: translateY(-20px); }
            80% { opacity: 1; transform: translateY(2px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MemoryChain - Modern Homepage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        
        .chain-logo {
            color: #111;
            transition: color 0.5s;
        }
        
        .memorychain-logo {
            background: none;
            color: #111;
            background-clip: unset;
            -webkit-background-clip: unset;
            -webkit-text-fill-color: unset;
            transition: color 0.5s;
            opacity: 0;
            animation: logoFadeIn 1.2s ease-in forwards;
        }
        .chain-logo {
            opacity: 0;
            animation: logoFadeIn 1.2s ease-in forwards;
        }
        @media (max-width: 640px) {
            .chain-logo {
                animation: chainColorCycle 3s infinite linear;
                animation-delay: 1.2s;
            }
            .memorychain-logo {
                color: unset;
                background: linear-gradient(90deg, #6366f1, #06b6d4, #f59e0b, #ef4444, #6366f1);
                background-size: 400% 100%;
                background-clip: text;
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                animation: logoFadeIn 1.2s ease-in forwards, memorychainColorCycle 3s infinite linear;
                animation-delay: 0s, 1.2s;
            }
        }
        @keyframes chainColorCycle {
            0% { color: #6366f1; }
            25% { color: #06b6d4; }
            50% { color: #f59e0b; }
            75% { color: #ef4444; }
            100% { color: #6366f1; }
        }
        @keyframes memorychainColorCycle {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @keyframes logoFadeIn {
            0% { opacity: 0; transform: translateY(-20px) scale(0.95); }
            80% { opacity: 1; transform: translateY(2px) scale(1.03); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        html, body {
            width: 100vw;
            min-height: 100vh;
            max-width: 100vw;
            overflow-x: hidden;
        }
        .feature-card, .testimonial-card {
            min-width: 0;
        }
        @media (max-width: 1024px) {
            .max-w-7xl {
                max-width: 100vw !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.2rem !important;
            }
            .text-5xl {
                font-size: 2rem !important;
            }
            .text-4xl {
                font-size: 1.5rem !important;
            }
            .feature-card, .testimonial-card {
                padding: 1.5rem !important;
            }
            .py-20, .md\:py-32 {
                padding-top: 3rem !important;
                padding-bottom: 3rem !important;
            }
            .py-16, .md\:py-24 {
                padding-top: 2rem !important;
                padding-bottom: 2rem !important;
            }
        }
        @media (max-width: 480px) {
            .hero-title, .text-5xl {
                font-size: 1.3rem !important;
            }
            .text-xl, .md\:text-2xl {
                font-size: 1rem !important;
            }
            .feature-card, .testimonial-card {
                padding: 1rem !important;
            }
        }
        @media (max-width: 640px) {
            #chatbot-widget { right: 2vw; bottom: 2vw; }
            #chatbot-panel { width: 95vw !important; max-width: 95vw !important; left: 0; right: 0; margin: 0 auto; }
        }
        @media (max-width: 400px) {
            #chatbot-panel { padding: 1vw !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-white to-slate-100 font-sans page-fadein">
    <!-- Dynamic Greeting Section Removed -->
       
    
    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md shadow-lg sticky top-0 z-50 w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="flex flex-wrap justify-between items-center py-4 w-full">
                <a href="modern_homepage.php" class="flex items-center space-x-2 min-w-0">
                    <i class="fas fa-link text-2xl chain-logo"></i>
                    <span class="text-2xl font-bold memorychain-logo whitespace-nowrap">MemoryChain</span>
                </a>
                <button class="sm:hidden block text-2xl p-2" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
                <div class="flex items-center space-x-4 min-w-0 hidden sm:flex" id="desktopMenu">
                    <a href="register.php" class="text-blue-600 hover:text-blue-800 font-medium transition-colors whitespace-nowrap">Register</a>
                    <a href="signin.php" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2 rounded-lg font-medium hover:from-blue-700 hover:to-indigo-700 transition-all whitespace-nowrap">
                        Sign In
                    </a>
                </div>
            </div>
            <div class="w-full flex flex-col items-center mt-2 sm:hidden hidden" id="mobileMenu">
                <a href="register.php" class="text-blue-600 hover:text-blue-800 font-medium transition-colors whitespace-nowrap mb-2">Register</a>
                <a href="signin.php" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2 rounded-lg font-medium hover:from-blue-700 hover:to-indigo-700 transition-all whitespace-nowrap">Sign In</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-700 text-white py-16 md:py-32">
        <div class="absolute inset-0 bg-black/20"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl md:text-7xl font-bold mb-4 leading-tight">
                    Welcome to <span class="bg-gradient-to-r from-yellow-300 to-pink-300 bg-clip-text text-transparent">MemoryChain</span>
                </h1>
                <p class="text-base md:text-2xl mb-6 max-w-3xl mx-auto">
                    Your memories, secured and organized like never before. Experience the future of memory management.
                </p>
                <div class="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-4">
                    <a href="register.php" class="bg-gradient-to-r from-yellow-400 to-pink-400 text-blue-900 px-6 py-2 rounded-lg font-semibold hover:from-yellow-300 hover:to-pink-300 transition-all shadow-lg">
                        Get Started Free
                    </a>
                    <a href="signin.php" class="bg-white/20 backdrop-blur-sm text-white px-6 py-2 rounded-lg font-semibold hover:bg-white/30 transition-all border border-white/30">
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-12 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <h2 class="text-2xl md:text-4xl font-bold text-gray-900 mb-2">Why Choose MemoryChain?</h2>
                <p class="text-base md:text-xl text-gray-600 max-w-3xl mx-auto">
                    Experience the future of memory management with cutting-edge features
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Feature 1 -->
                <div class="bg-white p-8 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-100">
                    <div class="flex items-center mb-6">
                        <div class="p-3 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full">
                            <i class="fas fa-shield-alt text-white text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 ml-4">Secure Storage</h3>
                    </div>
                    <p class="text-gray-600 leading-relaxed">
                        Your memories are encrypted with military-grade security, ensuring complete privacy and protection.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white p-8 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-100">
                    <div class="flex items-center mb-6">
                        <div class="p-3 bg-gradient-to-r from-green-500 to-emerald-500 rounded-full">
                            <i class="fas fa-mobile-alt text-white text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 ml-4">Anywhere Access</h3>
                    </div>
                    <p class="text-gray-600 leading-relaxed">
                        Access your memories from any device, anywhere in the world with seamless synchronization.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white p-8 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-100">
                    <div class="flex items-center mb-6">
                        <div class="p-3 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full">
                            <i class="fas fa-sync-alt text-white text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 ml-4">Seamless Sync</h3>
                    </div>
                    <p class="text-gray-600 leading-relaxed">
                        Sync your memories across all devices seamlessly with real-time updates and backup.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 md:py-20 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl md:text-4xl font-bold mb-4">Ready to Transform Your Memory Management?</h2>
            <p class="text-base md:text-xl mb-6 max-w-3xl mx-auto">
                Join thousands of users who trust us with their memories and experience the future today.
            </p>
            <div class="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-4">
                <a href="register.php" class="bg-gradient-to-r from-yellow-400 to-pink-400 text-blue-900 px-6 py-2 rounded-lg font-semibold hover:from-yellow-300 hover:to-pink-300 transition-all shadow-lg">
                    Start Free Trial
                </a>
                <a href="signin.php" class="bg-white/20 backdrop-blur-sm text-white px-6 py-2 rounded-lg font-semibold hover:bg-white/30 transition-all border border-white/30">
                    Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <h3 class="text-xl font-bold mb-4">MemoryChain</h3>
                    <p class="text-gray-300">Your memories, secured and organized like never before.</p>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="register.php" class="text-gray-300 hover:text-white transition-colors">Register</a></li>
                        <li><a href="signin.php" class="text-gray-300 hover:text-white transition-colors">Sign In</a></li>
                        <li><a href="dashboard.php" class="text-gray-300 hover:text-white transition-colors">Dashboard</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Help Center</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Contact Us</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">Connect</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-300 hover:text-white transition-colors"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="mt-6 pt-6 border-t border-gray-700 text-center">
                <p class="text-gray-300 text-xs">&copy; 2024 MemoryChain. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Chatbot Widget -->
    <div id="chatbot-widget" class="fixed bottom-6 right-6 z-50">
        <button id="chatbot-toggle" class="bg-indigo-600 text-white rounded-full shadow-lg p-4 hover:bg-indigo-700 focus:outline-none">
            <i class="fas fa-comments"></i>
        </button>
        <div id="chatbot-panel" class="hidden bg-white rounded-xl shadow-2xl w-80 max-w-full p-4 mt-2 border border-gray-200">
            <div class="flex items-center mb-2">
                <i class="fas fa-robot text-indigo-600 text-xl mr-2"></i>
                <span class="font-bold text-indigo-700">MemoryChain Assistant</span>
            </div>
            <div id="chatbot-messages" class="h-48 overflow-y-auto mb-2 text-sm text-gray-700 bg-gray-50 rounded p-2"></div>
            <form id="chatbot-form" class="flex">
                <input type="text" id="chatbot-input" class="flex-1 border border-gray-300 rounded-l px-2 py-1 focus:outline-none" placeholder="Ask me anything..." autocomplete="off">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-1 rounded-r hover:bg-indigo-700">Send</button>
            </form>
        </div>
    </div>
    <script>
    // Dynamic greeting for homepage removed
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });

        // Smooth scrolling for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card').forEach(card => {
            observer.observe(card);
        });

        // Chatbot widget logic
        const chatbotToggle = document.getElementById('chatbot-toggle');
        const chatbotPanel = document.getElementById('chatbot-panel');
        const chatbotForm = document.getElementById('chatbot-form');
        const chatbotInput = document.getElementById('chatbot-input');
        const chatbotMessages = document.getElementById('chatbot-messages');
        function getGreeting() {
            const hour = new Date().getHours();
            if (hour >= 1 && hour < 12) return 'Good morning';
            if (hour >= 12 && hour < 17) return 'Good afternoon';
            if (hour >= 17 && hour < 22) return 'Good evening';
            return 'Good night';
        }
        function detectLanguage(text) {
            // Simple detection for demonstration
            if (/bonjour|salut|merci|bienvenue/i.test(text)) return 'fr';
            if (/hola|buenos|gracias|bienvenido/i.test(text)) return 'es';
            if (/hallo|guten|danke|willkommen/i.test(text)) return 'de';
            if (/ciao|buongiorno|grazie|benvenuto/i.test(text)) return 'it';
            if (/你好|欢迎|谢谢/i.test(text)) return 'zh';
            return 'en';
        }
        function translateReply(reply, lang) {
            const translations = {
                'Good morning': { fr: 'Bonjour', es: 'Buenos días', de: 'Guten Morgen', it: 'Buongiorno', zh: '早上好' },
                'Good afternoon': { fr: 'Bon après-midi', es: 'Buenas tardes', de: 'Guten Tag', it: 'Buon pomeriggio', zh: '下午好' },
                'Good evening': { fr: 'Bonsoir', es: 'Buenas noches', de: 'Guten Abend', it: 'Buonasera', zh: '晚上好' },
                'Welcome to MemoryChain! How can I assist you today?': {
                    fr: 'Bienvenue sur MemoryChain ! Comment puis-je vous aider aujourd’hui ?',
                    es: '¡Bienvenido a MemoryChain! ¿Cómo puedo ayudarte hoy?',
                    de: 'Willkommen bei MemoryChain! Wie kann ich Ihnen heute helfen?',
                    it: 'Benvenuto su MemoryChain! Come posso aiutarti oggi?',
                    zh: '欢迎来到MemoryChain！我能为您做些什么？'
                },
                // Add more translations as needed
            };
            return translations[reply] && translations[reply][lang] ? translations[reply][lang] : reply;
        }
        chatbotToggle.addEventListener('click', () => {
            chatbotPanel.classList.toggle('hidden');
            if (!chatbotPanel.classList.contains('hidden') && chatbotMessages.childElementCount === 0) {
                const greet = getGreeting();
                const welcome = greet + '! Welcome to MemoryChain! How can I assist you today?';
                appendMessage('Assistant', welcome);
            }
        });
        chatbotForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const userMsg = chatbotInput.value.trim();
            if (!userMsg) return;
            appendMessage('You', userMsg);
            chatbotInput.value = '';
            setTimeout(() => {
                const lang = detectLanguage(userMsg);
                let reply = getBotReply(userMsg);
                if (lang !== 'en') reply = translateReply(reply, lang);
                appendMessage('Assistant', reply);
            }, 600);
        });
        function appendMessage(sender, text) {
            const msgDiv = document.createElement('div');
            msgDiv.className = 'mb-1';
            msgDiv.innerHTML = `<span class='font-semibold'>${sender}:</span> ${text}`;
            chatbotMessages.appendChild(msgDiv);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }
        function getBotReply(msg) {
            msg = msg.toLowerCase();
            if (msg.includes('hello') || msg.includes('hi') || msg.includes('bonjour') || msg.includes('hola') || msg.includes('hallo') || msg.includes('ciao') || msg.includes('你好')) return getGreeting() + '! How can I assist you today?';
            if (msg.includes('register')) return 'To register, click the Register button at the top right.';
            if (msg.includes('forgot password')) return 'Click "Forgot your password?" on the sign-in page.';
            if (msg.includes('vault')) return 'Your vault stores your memories securely. Access it from your dashboard.';
            if (msg.includes('2fa') || msg.includes('security')) return 'You can enable 2FA in your account settings for extra security.';
            if (msg.includes('sign in')) return 'Click the Sign In button at the top right to access your account.';
            if (msg.includes('features')) return 'MemoryChain offers secure storage, anywhere access, and seamless sync.';
            if (msg.includes('purpose') || msg.includes('what is this') || msg.includes('about') || msg.includes('website for')) return 'MemoryChain is designed to help you securely store, organize, and manage your personal memories and important information. It offers encrypted storage, easy access from any device, and advanced security features.';
            return 'I am here to help! Please ask about registration, sign-in, vault, or security.';
        }
    </script>
</body>
</html>
