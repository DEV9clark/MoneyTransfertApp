import 'package:flutter/material.dart';
import 'dart:math' as math;
import 'dart:ui' as ui;
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import 'dashboard_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  _LoginScreenState createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with TickerProviderStateMixin {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _apiService = ApiService();
  bool _isLoading = false;
  bool _isPasswordVisible = false;

  late AnimationController _pulseController;
  late AnimationController _iconController;

  @override
  void initState() {
    super.initState();
    // Background circles pulse animation
    _pulseController = AnimationController(
      duration: const Duration(seconds: 4),
      vsync: this,
    )..repeat(reverse: true);

    // Login icon animation (orbit)
    _iconController = AnimationController(
      duration: const Duration(seconds: 3),
      vsync: this,
    )..repeat();
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _iconController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _login() async {
    setState(() => _isLoading = true);

    try {
      await _apiService.login(
        _emailController.text,
        _passwordController.text,
      );
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (context) => const DashboardScreen()),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString()),
            backgroundColor: Colors.redAccent,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;

    return Scaffold(
      backgroundColor: AppTheme.darkBg,
      body: Stack(
        children: [
          // 1. Animated Background Elements
          Positioned(
            top: -60,
            right: -60,
            child: _buildAnimatedCircle(size.width * 0.6, AppTheme.primaryPurple.withOpacity(0.15)),
          ),
          Positioned(
            top: size.height * 0.2,
            left: -40,
            child: _buildAnimatedCircle(size.width * 0.4, AppTheme.primaryBlue.withOpacity(0.1), delay: 2.0),
          ),

          // 2. Main Scrollable Content
          SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                return SingleChildScrollView(
                  child: ConstrainedBox(
                    constraints: BoxConstraints(
                      minHeight: constraints.maxHeight,
                    ),
                    child: IntrinsicHeight(
                      child: Column(
                        children: [
                          // Header / Illustration Area (Top ~35%)
                          Expanded(
                            flex: 4,
                            child: Container(
                              alignment: Alignment.center,
                              padding: const EdgeInsets.symmetric(vertical: 40),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  _buildLoginIcon(),
                                  const SizedBox(height: 30),
                                  Text(
                                    'Welcome Back',
                                    style: AppTheme.titleStyle.copyWith(fontSize: 28),
                                  ),
                                  const SizedBox(height: 10),
                                  Text(
                                    'Sign in to continue',
                                    style: TextStyle(color: Colors.white.withOpacity(0.6), fontSize: 16),
                                  ),
                                ],
                              ),
                            ),
                          ),

                          // Content Area (Middle & Bottom - Card Style)
                          Expanded(
                            flex: 6,
                            child: ClipRRect(
                              borderRadius: const BorderRadius.only(
                                topLeft: Radius.circular(50),
                                topRight: Radius.circular(50),
                              ),
                              child: BackdropFilter(
                                filter:  ui.ImageFilter.blur(sigmaX: 10, sigmaY: 10),
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: AppTheme.darkBg.withOpacity(0.5), // Semi-transparent Glass effect
                                    borderRadius: const BorderRadius.only(
                                      topLeft: Radius.circular(50),
                                      topRight: Radius.circular(50),
                                    ),
                                    border: Border(
                                      top: BorderSide(color: Colors.white.withOpacity(0.1), width: 1),
                                      left: BorderSide(color: Colors.white.withOpacity(0.05), width: 0.5),
                                      right: BorderSide(color: Colors.white.withOpacity(0.05), width: 0.5),
                                    ),
                                  ),

                              padding: const EdgeInsets.fromLTRB(30, 50, 30, 30),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // Email Field
                                  Text(
                                    'Email',
                                    style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 14, fontWeight: FontWeight.bold),
                                  ),
                                  const SizedBox(height: 10),
                                  _buildInputContainer(
                                    child: TextField(
                                      controller: _emailController,
                                      style: const TextStyle(color: Colors.white),
                                      decoration: const InputDecoration(
                                        border: InputBorder.none,
                                        hintText: 'user@example.com',
                                        hintStyle: TextStyle(color: Colors.white24),
                                        icon: Icon(Icons.email_outlined, color: AppTheme.accentTeal),
                                      ),
                                      keyboardType: TextInputType.emailAddress,
                                    ),
                                  ),
                                  
                                  const SizedBox(height: 25),

                                  // Password Field
                                  Text(
                                    'Password',
                                    style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 14, fontWeight: FontWeight.bold),
                                  ),
                                  const SizedBox(height: 10),
                                  _buildInputContainer(
                                    child: TextField(
                                      controller: _passwordController,
                                      obscureText: !_isPasswordVisible,
                                      style: const TextStyle(color: Colors.white),
                                      decoration: InputDecoration(
                                        border: InputBorder.none,
                                        hintText: '••••••••',
                                        hintStyle: const TextStyle(color: Colors.white24),
                                        icon: const Icon(Icons.lock_outline, color: AppTheme.accentTeal),
                                        suffixIcon: IconButton(
                                          icon: Icon(
                                            _isPasswordVisible ? Icons.visibility : Icons.visibility_off,
                                            color: Colors.white38,
                                          ),
                                          onPressed: () => setState(() => _isPasswordVisible = !_isPasswordVisible),
                                        ),
                                      ),
                                    ),
                                  ),

                                  // Forgot Password
                                  Align(
                                    alignment: Alignment.centerRight,
                                    child: TextButton(
                                      onPressed: () {},
                                      child: Text(
                                        'Forgot Password?',
                                        style: TextStyle(color: AppTheme.primaryPurple.withOpacity(0.8), fontWeight: FontWeight.w600),
                                      ),
                                    ),
                                  ),

                                  const SizedBox(height: 30),

                                  // Login Button
                                  _isLoading
                                      ? const Center(child: CircularProgressIndicator(color: AppTheme.accentTeal))
                                      : Container(
                                          width: double.infinity,
                                          height: 60,
                                          decoration: BoxDecoration(
                                            gradient: AppTheme.primaryGradient,
                                            borderRadius: BorderRadius.circular(20),
                                            boxShadow: [
                                              BoxShadow(
                                                color: AppTheme.primaryBlue.withOpacity(0.5),
                                                blurRadius: 15,
                                                offset: const Offset(0, 8),
                                              ),
                                            ],
                                          ),
                                          child: ElevatedButton(
                                            onPressed: _login,
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor: Colors.transparent,
                                              shadowColor: Colors.transparent,
                                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                                            ),
                                            child: const Text('LOGIN', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, letterSpacing: 1.5)),
                                          ),
                                        ),

                                  const Spacer(),

                                  // Footer
                                  Center(
                                    child: Row(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Text("Don't have an account? ", style: TextStyle(color: Colors.white.withOpacity(0.6))),
                                        GestureDetector(
                                          onTap: () {
                                            // Navigation to register
                                          },
                                          child: const Text(
                                            "Sign up",
                                            style: TextStyle(
                                              color: AppTheme.accentTeal,
                                              fontWeight: FontWeight.bold,
                                              decoration: TextDecoration.underline,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(height: 20),
                                ],
                          ), // End Column
                          ), // End Container
                          ), // End BackdropFilter
                          ), // End ClipRRect
                          ), // End Expanded
                        ],
                      ),
                    ),
                  ),
                );
              }
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInputContainer({required Widget child}) {
    return Container(
      decoration: BoxDecoration(
        color: AppTheme.darkBg,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: Colors.white.withOpacity(0.1)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 5),
      child: child,
    );
  }

  // Helper widget for animated background circles
  Widget _buildAnimatedCircle(double size, Color color, {double delay = 0.0}) {
    return AnimatedBuilder(
      animation: _pulseController,
      builder: (context, child) {
        final value = math.sin((_pulseController.value * 2 * math.pi) + delay);
        final scale = 1.0 + (value * 0.1); 
        
        return Transform.scale(
          scale: scale,
          child: Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: color,
              boxShadow: [
                BoxShadow(
                  color: color,
                  blurRadius: 50,
                  spreadRadius: 20,
                )
              ],
            ),
          ),
        );
      },
    );
  }

  // Custom Animated Login Icon
  Widget _buildLoginIcon() {
    return SizedBox(
      width: 250,
      height: 250,
      child: Image.asset(
        'assets/images/login_illustration.png',
        fit: BoxFit.contain,
      ),
    );
  }
}
