import 'package:flutter/material.dart';
import 'dart:math' as math;
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class WithdrawScreen extends StatefulWidget {
  const WithdrawScreen({super.key});

  @override
  _WithdrawScreenState createState() => _WithdrawScreenState();
}

class _WithdrawScreenState extends State<WithdrawScreen> with TickerProviderStateMixin {
  final _codeController = TextEditingController();
  final _apiService = ApiService();
  bool _isLoading = false;

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

    // Transaction icon animation
    _iconController = AnimationController(
      duration: const Duration(seconds: 2),
      vsync: this,
    )..repeat();
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _iconController.dispose();
    _codeController.dispose();
    super.dispose();
  }

  void _withdraw() async {
    if (_codeController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a transaction code'), backgroundColor: Colors.red),
      );
      return;
    }

    setState(() => _isLoading = true);

    try {
      final result = await _apiService.withdraw(
        _codeController.text,
      );

      if (mounted) {
        showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            backgroundColor: AppTheme.darkBg,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            title: Text('Success', style: AppTheme.titleStyle.copyWith(fontSize: 20)),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Withdrawal successful!', style: TextStyle(color: Colors.white70)),
                const SizedBox(height: 10),
                Text(
                  'Amount: ${result['data']['amount']} ${result['data']['currency']}',
                  style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 18),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () {
                  Navigator.of(ctx).pop();
                  Navigator.of(context).pop();
                },
                child: const Text('OK', style: TextStyle(color: AppTheme.accentTeal)),
              )
            ],
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    // Screen height helper
    final size = MediaQuery.of(context).size;
    
    return Scaffold(
      backgroundColor: AppTheme.darkBg,
      body: Stack(
        children: [
          // 1. Animated Background Elements
          Positioned(
            top: -50,
            left: -50,
            child: _buildAnimatedCircle(size.width * 0.5, AppTheme.primaryBlue.withOpacity(0.1)),
          ),
          Positioned(
            top: size.height * 0.15,
            right: -30,
            child: _buildAnimatedCircle(size.width * 0.3, AppTheme.accentTeal.withOpacity(0.05), delay: 1.0),
          ),

          // 2. Main Scrollable Content
          SafeArea(
            child: SingleChildScrollView(
              child: SizedBox(
                height: size.height - MediaQuery.of(context).padding.top, // Ensure full height for layout
                child: Column(
                  children: [
                    // Header / Illustration Area (Top 35-40%)
                    Expanded(
                      flex: 4,
                      child: Container(
                        width: double.infinity,
                        alignment: Alignment.center,
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            // Back button
                            Padding(
                              padding: const EdgeInsets.only(left: 20.0, top: 10.0),
                              child: Align(
                                alignment: Alignment.topLeft,
                                child: IconButton(
                                  icon: const Icon(Icons.arrow_back_ios, color: Colors.white),
                                  onPressed: () => Navigator.of(context).pop(),
                                ),
                              ),
                            ),
                            const Spacer(),
                            // Animated Transaction Icon
                            _buildTransactionIcon(),
                            const SizedBox(height: 20),
                            Text(
                              'Withdraw Money',
                              style: AppTheme.titleStyle.copyWith(fontSize: 24),
                            ),
                            const SizedBox(height: 10),
                            Text(
                              'Secure Transfer',
                              style: TextStyle(color: Colors.white.withOpacity(0.6), fontSize: 14),
                            ),
                            const Spacer(),
                          ],
                        ),
                      ),
                    ),

                    // Content Area (Middle & Bottom)
                    Expanded(
                      flex: 6,
                      child: Container(
                        width: double.infinity,
                        decoration: BoxDecoration(
                          color: AppTheme.cardBg, // Slightly lighter dark background
                          borderRadius: const BorderRadius.only(
                            topLeft: Radius.circular(40),
                            topRight: Radius.circular(40),
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.3),
                              blurRadius: 20,
                              offset: const Offset(0, -5),
                            ),
                          ],
                        ),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 30.0, vertical: 40.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Transaction Code',
                                style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 16, fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 10),
                              Container(
                                decoration: BoxDecoration(
                                  color: AppTheme.darkBg,
                                  borderRadius: BorderRadius.circular(15),
                                  border: Border.all(color: Colors.white.withOpacity(0.1)),
                                ),
                                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 5),
                                child: TextField(
                                  controller: _codeController,
                                  style: const TextStyle(color: Colors.white, fontSize: 18, letterSpacing: 1.5),
                                  decoration: const InputDecoration(
                                    border: InputBorder.none,
                                    hintText: 'XYZ-123',
                                    hintStyle: TextStyle(color: Colors.white24),
                                    icon: Icon(Icons.lock_outline_rounded, color: AppTheme.accentTeal),
                                  ),
                                  textCapitalization: TextCapitalization.characters,
                                ),
                              ),
                              
                              const SizedBox(height: 20),
                              Text(
                                'Enter the code received from the sender to verify and withdraw funds immediately.',
                                style: TextStyle(color: Colors.white.withOpacity(0.4), fontSize: 13),
                              ),

                              const Spacer(),

                              // Action Button
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
                                        onPressed: _withdraw,
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: Colors.transparent,
                                          shadowColor: Colors.transparent,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                                        ),
                                        child: const Row(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: [
                                            Text('VERIFY & WITHDRAW', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, letterSpacing: 1)),
                                            SizedBox(width: 10),
                                            Icon(Icons.arrow_forward_rounded, size: 20),
                                          ],
                                        ),
                                      ),
                                    ),
                              const SizedBox(height: 20),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // Helper widget for animated background circles
  Widget _buildAnimatedCircle(double size, Color color, {double delay = 0.0}) {
    return AnimatedBuilder(
      animation: _pulseController,
      builder: (context, child) {
        // Create a staggered sine wave effect
        final value = math.sin((_pulseController.value * 2 * math.pi) + delay);
        final scale = 1.0 + (value * 0.15); // Scale between 0.85 and 1.15
        
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
                  blurRadius: 30,
                  spreadRadius: 10,
                )
              ],
            ),
          ),
        );
      },
    );
  }

  // Custom Animated Transaction Icon
  Widget _buildTransactionIcon() {
    return SizedBox(
      width: 120,
      height: 120,
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Central Circle/Wallet
          Container(
            width: 80,
            height: 80,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                colors: [AppTheme.primaryBlue.withOpacity(0.8), AppTheme.primaryPurple],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              boxShadow: [
                BoxShadow(
                   color: AppTheme.primaryPurple.withOpacity(0.4),
                   blurRadius: 20,
                   spreadRadius: 5,
                )
              ]
            ),
            child: const Icon(Icons.account_balance_wallet_rounded, color: Colors.white, size: 40),
          ),
          
          // Orbiting/Moving Elements
          AnimatedBuilder(
            animation: _iconController,
            builder: (context, child) {
              return Stack(
                children: [
                  // Item 1: Incoming arrow (moving towards center)
                  Positioned(
                    top: 10 + (10 * math.sin(_iconController.value * 2 * math.pi)),
                    right: 15,
                    child: Transform.rotate(
                      angle: -math.pi / 4,
                      child: const Icon(Icons.arrow_downward_rounded, color: AppTheme.accentTeal, size: 28),
                    ),
                  ),
                  // Item 2: Coin/Dot orbiting
                   Transform.translate(
                    offset: Offset(
                      40 * math.cos(_iconController.value * 2 * math.pi),
                      40 * math.sin(_iconController.value * 2 * math.pi),
                    ),
                    child: Container(
                      width: 12,
                      height: 12,
                      decoration: const BoxDecoration(
                        color: Colors.amber,
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}
