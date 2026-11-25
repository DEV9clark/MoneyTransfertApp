/// Configuration de l'application client.
///
/// L'URL de l'API se change sans toucher au code :
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
/// (10.0.2.2 = la machine hôte depuis l'émulateur Android)
class AppConfig {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://unwesternized-partis-lilli.ngrok-free.dev/api',
  );

  /// Pays présélectionné à l'ouverture (code ISO).
  static const String defaultCountryCode = 'SN';

  static const Duration requestTimeout = Duration(seconds: 25);
}
