import 'package:flutter/material.dart';
import '../services/api_service.dart';
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
    setState(() {
      _isLoading = true;
    });
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
          SnackBar(content: Text('Error loading data: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
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
      appBar: AppBar(
        title: const Text('Money Transfer'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadData,
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _logout,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: Column(
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      children: [
                        const Text(
                          'Current Balance',
                          style: TextStyle(fontSize: 16),
                        ),
                        Text(
                          '\$${_balance.toStringAsFixed(2)}',
                          style: const TextStyle(
                              fontSize: 32, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                  ),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      ElevatedButton.icon(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                                builder: (context) => const SendMoneyScreen()),
                          ).then((_) => _loadData());
                        },
                        icon: const Icon(Icons.send),
                        label: const Text('Send'),
                      ),
                      ElevatedButton.icon(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                                builder: (context) => const WithdrawScreen()),
                          ).then((_) => _loadData());
                        },
                        icon: const Icon(Icons.download),
                        label: const Text('Withdraw'),
                      ),
                    ],
                  ),
                  const Padding(
                    padding: EdgeInsets.all(16.0),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        'Recent Transactions',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                  Expanded(
                    child: ListView.builder(
                      itemCount: _transactions.length,
                      itemBuilder: (context, index) {
                        final tx = _transactions[index];
                        return ListTile(
                          leading: Icon(
                            tx['type'] == 'transfer'
                                ? Icons.arrow_upward
                                : Icons.arrow_downward,
                            color: tx['type'] == 'transfer'
                                ? Colors.red
                                : Colors.green,
                          ),
                          title: Text('${tx['type'].toString().toUpperCase()}'),
                          subtitle: Text('Status: ${tx['status']}'),
                          trailing: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                '\$${tx['amount']}',
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold),
                              ),
                              if (tx['reference'] != null)
                                Text(
                                  'Ref: ${tx['reference']}',
                                  style: const TextStyle(fontSize: 12),
                                ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
