import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import 'login_screen.dart';
import 'send_money_screen.dart';
import 'withdraw_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  _DashboardScreenState createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _apiService = ApiService();
  double _balance = 0.0;
  List<dynamic> _transactions = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final balance = await _apiService.getBalance();
      final transactions = await _apiService.getTransactions();
      setState(() {
        _balance = balance;
        _transactions = transactions;
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _logout() async {
    await _apiService.logout();
    if (mounted) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => const LoginScreen()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.darkBg, // 🌑 Dark Background
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.accentTeal))
          : RefreshIndicator(
              onRefresh: _loadData,
              color: AppTheme.accentTeal,
              child: CustomScrollView(
                slivers: [
                  // 🏁 HEADER APP BAR
                  SliverAppBar(
                    backgroundColor: AppTheme.darkBg,
                    elevation: 0,
                    pinned: true,
                    expandedHeight: 80.0,
                    flexibleSpace: FlexibleSpaceBar(
                      titlePadding: const EdgeInsets.only(left: 20, bottom: 15),
                      title: Text(
                        'My Dashboard',
                        style: AppTheme.titleStyle.copyWith(fontSize: 22),
                      ),
                    ),
                    actions: [
                      IconButton(
                        icon: const Icon(Icons.logout, color: Colors.white70),
                        onPressed: _logout,
                      ),
                    ],
                  ),

                  // 💳 BALANCE CARD SECTION
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.all(20.0),
                      child: Container(
                        height: 200,
                        decoration: BoxDecoration(
                          gradient: AppTheme.primaryGradient, // 🌈 Premium Gradient
                          borderRadius: BorderRadius.circular(25),
                          boxShadow: [
                            BoxShadow(
                              color: AppTheme.primaryBlue.withOpacity(0.5),
                              blurRadius: 20,
                              offset: const Offset(0, 10),
                            ),
                          ],
                        ),
                        child: Stack(
                          children: [
                            // Background Circles Decoration
                            Positioned(
                              top: -20,
                              right: -20,
                              child: CircleAvatar(
                                radius: 80,
                                backgroundColor: Colors.white.withOpacity(0.1),
                              ),
                            ),
                            Positioned(
                              bottom: -40,
                              left: -20,
                              child: CircleAvatar(
                                radius: 80,
                                backgroundColor: Colors.white.withOpacity(0.1),
                              ),
                            ),
                            
                            // Card Content
                            Padding(
                              padding: const EdgeInsets.all(25.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text('Total Balance', style: AppTheme.subtitleStyle),
                                      const Icon(Icons.credit_card, color: Colors.white70),
                                    ],
                                  ),
                                  Text(
                                    '\$${_balance.toStringAsFixed(2)}',
                                    style: AppTheme.titleStyle.copyWith(fontSize: 36),
                                  ),
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text('**** **** **** 4242', style: AppTheme.subtitleStyle),
                                      Text('EXP 12/28', style: AppTheme.subtitleStyle),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),

                  // ⚡ QUICK ACTIONS
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: _buildActionButton(
                              context,
                              'Send Money',
                              Icons.send_rounded,
                              AppTheme.accentTeal,
                              () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SendMoneyScreen())).then((_) => _loadData()),
                            ),
                          ),
                          const SizedBox(width: 15),
                          Expanded(
                            child: _buildActionButton(
                              context,
                              'Withdraw',
                              Icons.arrow_circle_down_rounded,
                              Colors.orangeAccent,
                              () => Navigator.push(context, MaterialPageRoute(builder: (_) => const WithdrawScreen())).then((_) => _loadData()),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  // 📜 RECENT TRANSACTIONS TITLE
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.all(20.0),
                      child: Text(
                        'Recent Transactions',
                        style: AppTheme.titleStyle.copyWith(fontSize: 20),
                      ),
                    ),
                  ),

                  // 📋 TRANSACTION LIST
                  SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final tx = _transactions[index];
                        final isSend = tx['type'] == 'send';
                        return Container(
                          margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.05),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: Colors.white.withOpacity(0.05)),
                          ),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: isSend 
                                      ? Colors.redAccent.withOpacity(0.2) 
                                      : AppTheme.accentTeal.withOpacity(0.2),
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  isSend ? Icons.arrow_upward_rounded : Icons.arrow_downward_rounded,
                                  color: isSend ? Colors.redAccent : AppTheme.accentTeal,
                                ),
                              ),
                              const SizedBox(width: 15),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      isSend ? 'Sent to ${tx['recipient_name'] ?? 'Unknown'}' : 'Withdrawal',
                                      style: AppTheme.buttonText.copyWith(fontSize: 16),
                                    ),
                                    Text(
                                      tx['created_at'] ?? 'Unknown Date',
                                      style: TextStyle(color: Colors.white54, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    '${isSend ? '-' : '+'}${tx['amount']} ${tx['currency'] ?? ''}',
                                    style: AppTheme.buttonText.copyWith(
                                      color: isSend ? Colors.redAccent : AppTheme.accentTeal,
                                    ),
                                  ),
                                  if (tx['status'] == 'pending')
                                    Container(
                                      margin: const EdgeInsets.only(top: 4),
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: Colors.orange.withOpacity(0.2),
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: const Text('Pending', style: TextStyle(color: Colors.orange, fontSize: 10)),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        );
                      },
                      childCount: _transactions.length,
                    ),
                  ),
                  const SliverPadding(padding: EdgeInsets.only(bottom: 30)),
                ],
              ),
            ),
    );
  }

  Widget _buildActionButton(BuildContext context, String label, IconData icon, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        // width was removed to allow Expanded to control width
        padding: const EdgeInsets.symmetric(vertical: 20),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.05),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.white.withOpacity(0.1)),
        ),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: color.withOpacity(0.2),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: color, size: 30),
            ),
            const SizedBox(height: 10),
            Text(label, style: AppTheme.buttonText.copyWith(fontSize: 14)),
          ],
        ),
      ),
    );
  }
}
