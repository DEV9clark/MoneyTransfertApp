import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import '../services/api.dart';

/// Reçu PDF généré par le serveur : affichage, partage (WhatsApp, email...) et impression.
class ReceiptScreen extends StatefulWidget {
  final String uuid;
  final String kind; // 'deposit' | 'withdrawal'
  final String? reference;

  const ReceiptScreen({super.key, required this.uuid, required this.kind, this.reference});

  @override
  State<ReceiptScreen> createState() => _ReceiptScreenState();
}

class _ReceiptScreenState extends State<ReceiptScreen> {
  late Future<Uint8List> _pdf;

  @override
  void initState() {
    super.initState();
    _pdf = Api.instance.receipt(widget.uuid, widget.kind);
  }

  @override
  Widget build(BuildContext context) {
    final isDeposit = widget.kind == 'deposit';
    return Scaffold(
      appBar: AppBar(title: Text(isDeposit ? "Reçu d'envoi" : 'Reçu de retrait')),
      body: FutureBuilder<Uint8List>(
        future: _pdf,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(snap.error.toString(), textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: () => setState(() => _pdf = Api.instance.receipt(widget.uuid, widget.kind)),
                      child: const Text('Réessayer'),
                    ),
                  ],
                ),
              ),
            );
          }
          final bytes = snap.data!;
          return PdfPreview(
            build: (_) async => bytes,
            pdfFileName: '${isDeposit ? 'recu-envoi' : 'recu-retrait'}-${widget.reference ?? widget.uuid}.pdf',
            canChangePageFormat: false,
            canChangeOrientation: false,
            canDebug: false,
          );
        },
      ),
    );
  }
}
