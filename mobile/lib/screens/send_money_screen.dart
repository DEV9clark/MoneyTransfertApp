import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class SendMoneyScreen extends StatefulWidget {
  const SendMoneyScreen({super.key});

  @override
  _SendMoneyScreenState createState() => _SendMoneyScreenState();
}

class _SendMoneyScreenState extends State<SendMoneyScreen> {
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _amountController = TextEditingController();
  final _destinationController = TextEditingController();
  final _recipientNameController = TextEditingController();
  final _recipientPhoneController = TextEditingController();
  
  final _apiService = ApiService();
  bool _isLoading = false;

  void _sendMoney() async {
    if (_nameController.text.isEmpty || 
        _phoneController.text.isEmpty || 
        _amountController.text.isEmpty ||
        _recipientNameController.text.isEmpty ||
        _recipientPhoneController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill all required fields'), backgroundColor: Colors.red),
      );
      return;
    }

    setState(() => _isLoading = true);

    try {
      final amount = double.parse(_amountController.text);
      final result = await _apiService.transfer(
        clientName: _nameController.text,
        clientPhone: _phoneController.text,
        amount: amount,
        type: 'send',
        destination: _destinationController.text.isNotEmpty ? _destinationController.text : 'Senegal',
        recipientName: _recipientNameController.text,
        recipientPhone: _recipientPhoneController.text,
      );
      
      if (mounted) {
        showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            backgroundColor: AppTheme.darkBg,
            title: Text('Success', style: AppTheme.titleStyle.copyWith(fontSize: 20)),
            content: Text(
              'Transfer initiated!\nTransaction Code: ${result['code']}',
              style: const TextStyle(color: Colors.white70),
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
    return Scaffold(
      backgroundColor: AppTheme.darkBg,
      appBar: AppBar(
        title: Text('Send Money', style: AppTheme.titleStyle.copyWith(fontSize: 20)),
        backgroundColor: AppTheme.darkBg,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: Padding(
        padding: const EdgeInsets.all(20.0),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Sender Details', style: AppTheme.subtitleStyle.copyWith(color: AppTheme.accentTeal, fontWeight: FontWeight.bold)),
              const SizedBox(height: 15),
              TextField(
                controller: _nameController,
                style: const TextStyle(color: Colors.white),
                decoration: AppTheme.inputDecoration('Client Name', Icons.person),
              ),
              const SizedBox(height: 15),
              TextField(
                controller: _phoneController,
                style: const TextStyle(color: Colors.white),
                decoration: AppTheme.inputDecoration('Client Phone', Icons.phone),
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 30),

              Text('Recipient Details', style: AppTheme.subtitleStyle.copyWith(color: AppTheme.accentTeal, fontWeight: FontWeight.bold)),
              const SizedBox(height: 15),
              TextField(
                controller: _recipientNameController,
                style: const TextStyle(color: Colors.white),
                decoration: AppTheme.inputDecoration('Recipient Name', Icons.person_outline),
              ),
              const SizedBox(height: 15),
              TextField(
                controller: _recipientPhoneController,
                style: const TextStyle(color: Colors.white),
                decoration: AppTheme.inputDecoration('Recipient Phone', Icons.phone_android),
                keyboardType: TextInputType.phone,
              ),
              const SizedBox(height: 15),
              TextField(
                controller: _destinationController,
                style: const TextStyle(color: Colors.white),
                decoration: AppTheme.inputDecoration('Destination (Optional)', Icons.location_on),
              ),

              const SizedBox(height: 30),
              Text('Amount', style: AppTheme.subtitleStyle.copyWith(color: AppTheme.accentTeal, fontWeight: FontWeight.bold)),
              const SizedBox(height: 15),
              TextField(
                controller: _amountController,
                style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
                decoration: AppTheme.inputDecoration('0.00', Icons.attach_money),
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
              ),

              const SizedBox(height: 40),
              _isLoading
                  ? const Center(child: CircularProgressIndicator(color: AppTheme.accentTeal))
                  : Container(
                      width: double.infinity,
                      height: 55,
                      decoration: BoxDecoration(
                        gradient: AppTheme.primaryGradient,
                        borderRadius: BorderRadius.circular(15),
                        boxShadow: [
                            BoxShadow(
                              color: AppTheme.primaryBlue.withOpacity(0.5),
                              blurRadius: 10,
                              offset: const Offset(0, 5),
                            ),
                          ],
                      ),
                      child: ElevatedButton(
                        onPressed: _sendMoney,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.transparent,
                          shadowColor: Colors.transparent,
                        ),
                        child: Text('Confirm Transfer', style: AppTheme.buttonText),
                      ),
                    ),
            ],
          ),
        ),
      ),
    );
  }
}
