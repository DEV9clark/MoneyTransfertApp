double _d(dynamic v) => v is num ? v.toDouble() : double.tryParse('${v ?? ''}') ?? 0;

int? _i(dynamic v) => v is int ? v : int.tryParse('${v ?? ''}');

DateTime? _dt(dynamic v) => v == null ? null : DateTime.tryParse(v.toString());

class Country {
  final int id;
  final String name;
  final String code;
  final String? phoneCode;

  const Country({required this.id, required this.name, required this.code, this.phoneCode});

  String get dialCode => phoneCode == null ? '' : '+${phoneCode!.replaceAll('+', '')}';

  factory Country.fromJson(Map<String, dynamic> j) => Country(
        id: _i(j['id']) ?? 0,
        name: '${j['name'] ?? ''}',
        code: '${j['code'] ?? ''}',
        phoneCode: j['phone_code']?.toString(),
      );
}

class ClientProfile {
  final String uuid;
  final String name;
  final String phone;
  final int? countryId;
  final String? phoneCode;

  const ClientProfile({required this.uuid, required this.name, required this.phone, this.countryId, this.phoneCode});

  String get firstName => name.trim().split(RegExp(r'\s+')).first;

  factory ClientProfile.fromJson(Map<String, dynamic> j) => ClientProfile(
        uuid: '${j['uuid'] ?? ''}',
        name: '${j['name'] ?? ''}',
        phone: '${j['phone'] ?? ''}',
        countryId: _i(j['country_id']),
        phoneCode: j['phone_code']?.toString(),
      );
}

class Quote {
  final double amount;
  final double fee;
  final double total;
  final String currency;

  const Quote({required this.amount, required this.fee, required this.total, required this.currency});

  factory Quote.fromJson(Map<String, dynamic> j) => Quote(
        amount: _d(j['amount']),
        fee: _d(j['fee']),
        total: _d(j['total']),
        currency: '${j['currency'] ?? 'XOF'}',
      );
}

class Beneficiary {
  final String name;
  final String phone;
  final int? countryId;
  final String? country;

  const Beneficiary({required this.name, required this.phone, this.countryId, this.country});

  factory Beneficiary.fromJson(Map<String, dynamic> j) => Beneficiary(
        name: '${j['name'] ?? ''}',
        phone: '${j['phone'] ?? ''}',
        countryId: _i(j['country_id']),
        country: j['country']?.toString(),
      );
}

/// Statuts d'un transfert vus par le client.
enum TransferStatus { awaitingPayment, pending, completed, cancelled, expired, unknown }

TransferStatus _status(String s) => switch (s) {
      'awaiting_payment' => TransferStatus.awaitingPayment,
      'pending' => TransferStatus.pending,
      'completed' => TransferStatus.completed,
      'cancelled' => TransferStatus.cancelled,
      'expired' => TransferStatus.expired,
      _ => TransferStatus.unknown,
    };

class Transfer {
  final String uuid;
  final String reference;
  final String? paymentCode;
  final TransferStatus status;
  final double amount;
  final double fee;
  final double total;
  final String currency;
  final String recipientName;
  final String recipientPhone;
  final String? destination;
  final int? destinationCountryId;
  final DateTime? createdAt;
  final DateTime? expiresAt;
  final DateTime? fundedAt;
  final DateTime? paidAt;
  final bool hasDepositReceipt;
  final bool hasWithdrawalReceipt;

  const Transfer({
    required this.uuid,
    required this.reference,
    required this.paymentCode,
    required this.status,
    required this.amount,
    required this.fee,
    required this.total,
    required this.currency,
    required this.recipientName,
    required this.recipientPhone,
    this.destination,
    this.destinationCountryId,
    this.createdAt,
    this.expiresAt,
    this.fundedAt,
    this.paidAt,
    this.hasDepositReceipt = false,
    this.hasWithdrawalReceipt = false,
  });

  factory Transfer.fromJson(Map<String, dynamic> j) => Transfer(
        uuid: '${j['uuid'] ?? ''}',
        reference: '${j['reference'] ?? ''}',
        paymentCode: j['payment_code']?.toString(),
        status: _status('${j['status'] ?? ''}'),
        amount: _d(j['amount']),
        fee: _d(j['fee']),
        total: _d(j['total']),
        currency: '${j['currency'] ?? 'XOF'}',
        recipientName: '${j['recipient_name'] ?? ''}',
        recipientPhone: '${j['recipient_phone'] ?? ''}',
        destination: j['destination']?.toString(),
        destinationCountryId: _i(j['destination_country_id']),
        createdAt: _dt(j['created_at']),
        expiresAt: _dt(j['expires_at']),
        fundedAt: _dt(j['funded_at']),
        paidAt: _dt(j['paid_at']),
        hasDepositReceipt: j['has_deposit_receipt'] == true,
        hasWithdrawalReceipt: j['has_withdrawal_receipt'] == true,
      );
}
