import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/api.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'send_screen.dart';
import 'transfer_screen.dart';
import 'welcome_screen.dart';

/// Accueil : un seul gros bouton « Envoyer » et la liste de mes envois.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _api = Api.instance;
  final _scroll = ScrollController();
  final List<Transfer> _items = [];
  int _page = 1;
  bool _hasMore = false;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 200) _loadMore();
    });
    _refresh();
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    setState(() => _error = null);
    try {
      final page = await _api.transfers(page: 1);
      if (!mounted) return;
      setState(() {
        _items
          ..clear()
          ..addAll(page.items);
        _page = 1;
        _hasMore = page.hasMore;
      });
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (!_hasMore || _loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      final page = await _api.transfers(page: _page + 1);
      if (!mounted) return;
      setState(() {
        _page++;
        _items.addAll(page.items);
        _hasMore = page.hasMore;
      });
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _open(Widget screen) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
    if (mounted) _refresh();
  }

  Future<void> _logout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Se déconnecter ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Se déconnecter')),
        ],
      ),
    );
    if (ok != true) return;
    await _api.logout();
    if (!mounted) return;
    Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => const WelcomeScreen()), (_) => false);
  }

  @override
  Widget build(BuildContext context) {
    final me = _api.me;
    final toPay = _items.where((t) => t.status == TransferStatus.awaitingPayment).toList();

    return Scaffold(
      appBar: AppBar(
        title: Text(me != null ? 'Bonjour ${me.firstName}' : 'Money Transfer'),
        actions: [
          PopupMenuButton<String>(
            onSelected: (v) {
              if (v == 'logout') _logout();
            },
            itemBuilder: (_) => [
              if (me != null)
                PopupMenuItem<String>(enabled: false, child: Text('${me.name}\n${me.phone}')),
              const PopupMenuItem<String>(value: 'logout', child: Text('Se déconnecter')),
            ],
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _open(const SendScreen()),
        icon: const Icon(Icons.send),
        label: const Text('Envoyer'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                controller: _scroll,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                children: [
                  if (_error != null)
                    Card(
                      color: Theme.of(context).colorScheme.errorContainer,
                      child: ListTile(
                        leading: const Icon(Icons.wifi_off),
                        title: Text(_error!),
                        trailing: TextButton(onPressed: _refresh, child: const Text('Réessayer')),
                      ),
                    ),
                  if (toPay.isNotEmpty) _ToPayBanner(count: toPay.length, onTap: () => _open(TransferScreen(uuid: toPay.first.uuid))),
                  if (_items.isEmpty && _error == null) _EmptyState(onSend: () => _open(const SendScreen())),
                  if (_items.isNotEmpty)
                    const Padding(
                      padding: EdgeInsets.fromLTRB(4, 16, 4, 8),
                      child: Text('Mes envois', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    ),
                  ..._items.map((t) => _TransferCard(transfer: t, onTap: () => _open(TransferScreen(uuid: t.uuid)))),
                  if (_loadingMore) const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator())),
                ],
              ),
            ),
    );
  }
}

class _ToPayBanner extends StatelessWidget {
  final int count;
  final VoidCallback onTap;

  const _ToPayBanner({required this.count, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Colors.orange.shade50,
      child: ListTile(
        onTap: onTap,
        leading: Icon(Icons.storefront, color: Colors.orange.shade800),
        title: Text(count == 1 ? '1 envoi à payer en agence' : '$count envois à payer en agence'),
        subtitle: const Text('Montrez le code de paiement à un agent.'),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final VoidCallback onSend;

  const _EmptyState({required this.onSend});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 48),
      child: Column(
        children: [
          Icon(Icons.outbox, size: 64, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 16),
          const Text('Aucun envoi pour le moment', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          const Text(
            '1. Préparez votre envoi ici\n2. Payez en espèces chez un agent\n3. Le bénéficiaire reçoit son code par SMS',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          FilledButton.icon(onPressed: onSend, icon: const Icon(Icons.send), label: const Text('Faire un envoi')),
        ],
      ),
    );
  }
}

class _TransferCard extends StatelessWidget {
  final Transfer transfer;
  final VoidCallback onTap;

  const _TransferCard({required this.transfer, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final t = transfer;
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              CircleAvatar(child: Text(t.recipientName.isNotEmpty ? t.recipientName[0].toUpperCase() : '?')),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(t.recipientName, maxLines: 1, overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                    const SizedBox(height: 4),
                    Text('${t.destination ?? ''} · ${Fmt.date(t.createdAt)}', style: const TextStyle(fontSize: 12)),
                    const SizedBox(height: 6),
                    StatusBadge(t.status),
                  ],
                ),
              ),
              Text(Fmt.money(t.amount, t.currency), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ],
          ),
        ),
      ),
    );
  }
}
