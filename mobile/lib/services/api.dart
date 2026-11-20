import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import '../config.dart';
import '../models/models.dart';

/// Erreur affichable telle quelle (message en français venant du serveur).
class ApiException implements Exception {
  final String message;
  final int? statusCode;

  ApiException(this.message, [this.statusCode]);

  @override
  String toString() => message;
}

/// Client HTTP de l'API "application client" (/api/client/...).
class Api {
  Api._();
  static final Api instance = Api._();

  static const _tokenKey = 'client_token';
  final _storage = const FlutterSecureStorage();

  /// Appelé quand la session expire : l'app revient à l'écran d'accueil.
  VoidCallback? onSessionExpired;

  ClientProfile? me;
  List<Country>? _countries;

  // ---------------------------------------------------------------------------
  // Bas niveau
  // ---------------------------------------------------------------------------

  Uri _uri(String path, [Map<String, String>? query]) {
    final base = AppConfig.apiBaseUrl.replaceAll(RegExp(r'/+$'), '');
    return Uri.parse('$base/client$path').replace(queryParameters: query);
  }

  Future<Map<String, String>> _headers({bool auth = true, String accept = 'application/json'}) async {
    final token = auth ? await _storage.read(key: _tokenKey) : null;
    return {
      'Accept': accept,
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Future<http.Response> _send(Future<http.Response> Function() call) async {
    try {
      return await call().timeout(AppConfig.requestTimeout);
    } on TimeoutException {
      throw ApiException('Le serveur ne répond pas. Vérifiez votre connexion.');
    } on http.ClientException {
      throw ApiException('Pas de connexion Internet.');
    }
  }

  Future<dynamic> _decode(http.Response res, {required bool auth}) async {
    dynamic body;
    try {
      body = res.body.isEmpty ? null : jsonDecode(utf8.decode(res.bodyBytes));
    } catch (_) {
      body = null;
    }

    if (res.statusCode >= 200 && res.statusCode < 300) return body;

    // 401 sur une route connectée = session expirée. (Sur la connexion, c'est un PIN incorrect.)
    if (res.statusCode == 401 && auth) {
      await _clearSession();
      onSessionExpired?.call();
      throw ApiException('Session expirée. Reconnectez-vous.', 401);
    }

    throw ApiException(_message(body, res.statusCode), res.statusCode);
  }

  String _message(dynamic body, int status) {
    if (body is Map) {
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) return first.first.toString();
      }
      final msg = body['message'];
      if (msg is String && msg.isNotEmpty) return msg;
    }
    if (status == 429) return 'Trop de tentatives. Patientez quelques minutes.';
    return status >= 500 ? 'Service momentanément indisponible.' : 'Erreur ($status).';
  }

  Future<dynamic> _get(String path, {Map<String, String>? query, bool auth = true}) async {
    final h = await _headers(auth: auth);
    return _decode(await _send(() => http.get(_uri(path, query), headers: h)), auth: auth);
  }

  Future<dynamic> _post(String path, [Map<String, dynamic>? body, bool auth = true]) async {
    final h = await _headers(auth: auth);
    return _decode(
      await _send(() => http.post(_uri(path), headers: h, body: jsonEncode(body ?? const {}))),
      auth: auth,
    );
  }

  Future<void> _saveSession(Map<String, dynamic> data) async {
    await _storage.write(key: _tokenKey, value: data['access_token'] as String);
    me = ClientProfile.fromJson(data['client'] as Map<String, dynamic>);
  }

  Future<void> _clearSession() async {
    await _storage.delete(key: _tokenKey);
    me = null;
  }

  // ---------------------------------------------------------------------------
  // Compte
  // ---------------------------------------------------------------------------

  Future<List<Country>> countries() async {
    if (_countries != null) return _countries!;
    final data = await _get('/countries', auth: false) as List;
    _countries = data.map((e) => Country.fromJson(e as Map<String, dynamic>)).toList();
    return _countries!;
  }

  Future<bool> isRegistered(int countryId, String phone) async {
    final data = await _post('/check', {'country_id': countryId, 'phone': phone}, false) as Map<String, dynamic>;
    return data['registered'] == true;
  }

  /// [purpose] : 'register' ou 'reset_pin'. Renvoie le code seulement en mode debug serveur.
  Future<String?> requestOtp(int countryId, String phone, String purpose) async {
    final data = await _post('/otp', {'country_id': countryId, 'phone': phone, 'purpose': purpose}, false)
        as Map<String, dynamic>;
    return data['debug_code']?.toString();
  }

  Future<void> register({
    required int countryId,
    required String phone,
    required String otp,
    required String name,
    required String pin,
  }) async {
    final data = await _post('/register', {
      'country_id': countryId,
      'phone': phone,
      'otp': otp,
      'name': name.trim(),
      'pin': pin,
      'pin_confirmation': pin,
    }, false) as Map<String, dynamic>;
    await _saveSession(data);
  }

  Future<void> login(int countryId, String phone, String pin) async {
    final data = await _post('/login', {'country_id': countryId, 'phone': phone, 'pin': pin}, false)
        as Map<String, dynamic>;
    await _saveSession(data);
  }

  Future<void> resetPin({required int countryId, required String phone, required String otp, required String pin}) async {
    final data = await _post('/pin/reset', {
      'country_id': countryId,
      'phone': phone,
      'otp': otp,
      'pin': pin,
      'pin_confirmation': pin,
    }, false) as Map<String, dynamic>;
    await _saveSession(data);
  }

  /// Reprend la session enregistrée. Null si aucune session valide.
  Future<ClientProfile?> restoreSession() async {
    if (await _storage.read(key: _tokenKey) == null) return null;
    // Au démarrage, une session expirée mène simplement à l'écran d'accueil (sans message).
    final callback = onSessionExpired;
    onSessionExpired = null;
    try {
      final data = await _get('/me') as Map<String, dynamic>;
      me = ClientProfile.fromJson(data);
      return me;
    } on ApiException catch (e) {
      if (e.statusCode == 401 || e.statusCode == 403) {
        await _clearSession();
        return null;
      }
      rethrow;
    } finally {
      onSessionExpired = callback;
    }
  }

  Future<void> logout() async {
    try {
      await _post('/logout');
    } catch (_) {
      // Déconnexion locale même hors ligne.
    } finally {
      await _clearSession();
    }
  }

  // ---------------------------------------------------------------------------
  // Transferts
  // ---------------------------------------------------------------------------

  Future<Quote> quote(double amount) async =>
      Quote.fromJson(await _post('/quote', {'amount': amount}) as Map<String, dynamic>);

  Future<List<Beneficiary>> beneficiaries() async {
    final data = await _get('/beneficiaries') as Map<String, dynamic>;
    return (data['data'] as List).map((e) => Beneficiary.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<({List<Transfer> items, bool hasMore})> transfers({int page = 1}) async {
    final data = await _get('/transfers', query: {'page': '$page'}) as Map<String, dynamic>;
    final items = (data['data'] as List).map((e) => Transfer.fromJson(e as Map<String, dynamic>)).toList();
    final current = (data['current_page'] as num?)?.toInt() ?? page;
    final last = (data['last_page'] as num?)?.toInt() ?? page;
    return (items: items, hasMore: current < last);
  }

  Future<Transfer> transfer(String uuid) async {
    final data = await _get('/transfers/$uuid') as Map<String, dynamic>;
    return Transfer.fromJson(data['data'] as Map<String, dynamic>);
  }

  Future<Transfer> createTransfer({
    required double amount,
    required int destinationCountryId,
    required String recipientName,
    required String recipientPhone,
  }) async {
    final data = await _post('/transfers', {
      'amount': amount,
      'destination_country_id': destinationCountryId,
      'recipient_name': recipientName.trim(),
      'recipient_phone': recipientPhone.trim(),
    }) as Map<String, dynamic>;
    return Transfer.fromJson(data['data'] as Map<String, dynamic>);
  }

  Future<Transfer> cancelTransfer(String uuid) async {
    final data = await _post('/transfers/$uuid/cancel') as Map<String, dynamic>;
    return Transfer.fromJson(data['data'] as Map<String, dynamic>);
  }

  /// [kind] : 'deposit' (reçu d'envoi) ou 'withdrawal' (reçu de retrait).
  Future<Uint8List> receipt(String uuid, String kind) async {
    final h = await _headers(accept: 'application/pdf, application/json');
    final res = await _send(() => http.get(_uri('/transfers/$uuid/receipt/$kind'), headers: h));
    if (res.statusCode == 200) return res.bodyBytes;
    await _decode(res, auth: true);
    throw ApiException('Reçu indisponible.');
  }
}
