import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  // Use 10.0.2.2 for Android Emulator, localhost for iOS Simulator
  static const String baseUrl = 'http://localhost:8080/api';
  final storage = const FlutterSecureStorage();

  Future<String?> getToken() async {
    return await storage.read(key: 'token');
  }

  Future<Map<String, dynamic>> register(String name, String email, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/register'),
      headers: {'Accept': 'application/json'},
      body: {'name': name, 'email': email, 'password': password},
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      await storage.write(key: 'token', value: data['access_token']);
      return data;
    } else {
      throw Exception('Failed to register: ${response.body}');
    }
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: {'Accept': 'application/json'},
      body: {'email': email, 'password': password},
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      await storage.write(key: 'token', value: data['access_token']);
      return data;
    } else {
      throw Exception('Failed to login: ${response.body}');
    }
  }

  Future<void> logout() async {
    final token = await getToken();
    if (token != null) {
      await http.post(
        Uri.parse('$baseUrl/logout'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );
      await storage.delete(key: 'token');
    }
  }

  Future<double> getBalance() async {
    final token = await getToken();
    final response = await http.get(
      Uri.parse('$baseUrl/balance'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return (data['balance'] as num).toDouble();
    } else {
      throw Exception('Failed to get balance');
    }
  }

  Future<List<dynamic>> getTransactions() async {
    final token = await getToken();
    final response = await http.get(
      Uri.parse('$baseUrl/transactions'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['data'];
    } else {
      throw Exception('Failed to get transactions');
    }
  }

  Future<Map<String, dynamic>> transfer({
    required String clientName,
    required String clientPhone,
    required double amount,
    required String type, // 'send' or 'withdraw'
    String? destination,
    String? recipientName,
    String? recipientPhone,
  }) async {
    final token = await getToken();
    
    final Map<String, dynamic> body = {
      'client_name': clientName,
      'client_phone': clientPhone,
      'amount': amount,
      'type': type,
    };

    if (type == 'send') {
       body['destination'] = destination ?? 'Senegal'; // Default or required
       body['recipient_name'] = recipientName;
       body['recipient_phone'] = recipientPhone;
    }

    final response = await http.post(
      Uri.parse('$baseUrl/transfer'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(body),
    );

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Failed to transfer: ${response.body}');
    }
  }

  Future<Map<String, dynamic>> withdraw(String code) async {
    final token = await getToken();
    final response = await http.post(
      Uri.parse('$baseUrl/withdraw'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'code': code}),
    );

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Failed to withdraw: ${response.body}');
    }
  }
}
