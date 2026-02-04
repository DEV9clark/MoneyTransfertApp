<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - TransferPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: '#4F46E5',
                        dark: '#111827',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-image: url('https://images.unsplash.com/photo-1639322537228-f710d846310a?q=80&w=2832&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
        }
        .glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="h-screen flex items-center justify-center p-4">

    <div class="glass w-full max-w-md p-8 rounded-2xl shadow-2xl transform transition-all hover:scale-[1.01]">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">TRANSFER<span class="text-primary">PRO</span></h1>
            <p class="text-gray-500 mt-2 text-sm">{{ __('Welcome back! Please enter your details.') }}</p>
        </div>

        <form id="loginForm" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" required 
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                    placeholder="Enter your email" value="admin@example.com"> <!-- Pre-filled for demo -->
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" required 
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all"
                    placeholder="••••••••" value="password">
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center text-gray-500 hover:text-gray-700 cursor-pointer">
                    <input type="checkbox" class="mr-2 rounded text-primary focus:ring-primary">
                    {{ __('Remember me') }}
                </label>
                <a href="#" class="font-medium text-primary hover:text-indigo-600">{{ __('Forgot password?') }}</a>
            </div>

            <button type="submit" id="submitBtn"
                class="w-full bg-dark text-white font-bold py-3 px-4 rounded-lg hover:bg-black focus:ring-4 focus: ring-gray-300 transition-all transform hover:-translate-y-0.5">
                {{ __('Sign In') }}
            </button>
        </form>

        <div id="alert" class="hidden mt-4 p-3 rounded-lg text-sm text-center"></div>

        <p class="text-center mt-8 text-sm text-gray-500">
            {{ __('Dont have an account?') }} <a href="#" class="font-bold text-primary hover:text-indigo-600">{{ __('Sign up') }}</a>
        </p>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const alert = document.getElementById('alert');
            
            // UI Loading State
            btn.innerHTML = '<svg class="animate-spin h-5 w-5 text-white mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            btn.disabled = true;
            alert.className = 'hidden';

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            try {
                const response = await fetch('/login', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (response.ok) {
                    // Success
                    alert.textContent = 'Login successful! Redirecting...';
                    alert.className = 'mt-4 p-3 rounded-lg text-sm text-center bg-green-100 text-green-800 block';
                    
                    setTimeout(() => {
                        window.location.href = data.redirect || '/dashboard';
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Login failed');
                }
            } catch (error) {
                alert.textContent = error.message;
                alert.className = 'mt-4 p-3 rounded-lg text-sm text-center bg-red-100 text-red-800 block';
                btn.innerHTML = 'Sign In';
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
