import 'package:flutter/material.dart';

import 'screens/home_screen.dart';
import 'screens/welcome_screen.dart';
import 'services/api.dart';

final navigatorKey = GlobalKey<NavigatorState>();

void main() {
  // Session expirée : retour à l'accueil depuis n'importe quel écran.
  Api.instance.onSessionExpired = () {
    navigatorKey.currentState?.pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const WelcomeScreen(message: 'Session expirée. Reconnectez-vous.')),
      (_) => false,
    );
  };
  runApp(const MoneyTransferClientApp());
}

class MoneyTransferClientApp extends StatelessWidget {
  const MoneyTransferClientApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'NISIS Transfert',
      navigatorKey: navigatorKey,
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF4F46E5)),
        inputDecorationTheme: InputDecorationTheme(
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
          filled: true,
        ),
        cardTheme: const CardThemeData(margin: EdgeInsets.symmetric(vertical: 6)),
      ),
      home: const _SessionGate(),
    );
  }
}

/// Au démarrage : session enregistrée → accueil directement, sinon écran du numéro.
class _SessionGate extends StatefulWidget {
  const _SessionGate();

  @override
  State<_SessionGate> createState() => _SessionGateState();
}

class _SessionGateState extends State<_SessionGate> {
  late Future<bool> _future;

  @override
  void initState() {
    super.initState();
    _future = _check();
  }

  Future<bool> _check() async => (await Api.instance.restoreSession()) != null;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<bool>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }
        if (snap.hasError) {
          return Scaffold(
            body: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.wifi_off, size: 48),
                  const SizedBox(height: 12),
                  Text(snap.error.toString(), textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () => setState(() => _future = _check()),
                    child: const Text('Réessayer'),
                  ),
                ],
              ),
            ),
          );
        }
        return snap.data == true ? const HomeScreen() : const WelcomeScreen();
      },
    );
  }
}
