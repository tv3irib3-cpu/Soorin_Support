import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'store.dart';

/// خطای API با پیامِ قابلِ‌نمایش و کدِ وضعیت.
class ApiException implements Exception {
  final int status;
  final String message;
  ApiException(this.status, this.message);
  @override
  String toString() => message;
}

/// کلاینتِ APIِ اپِ مشتری — همهٔ درخواست‌ها با توکنِ Bearerِ ماندگار.
/// endpointهای مخصوصِ مشتری زیرِ «portal/»؛ me/logout/attachments مشترک‌اند.
class Api {
  static const platform = 'portal';

  static Future<Uri> _uri(String path, [Map<String, dynamic>? query]) async {
    final base = await Store.baseUrl();
    return Uri.parse('$base/api/$path').replace(queryParameters: query);
  }

  static Future<Map<String, String>> _headers({bool auth = true, bool json = false}) async {
    final h = {'Accept': 'application/json'};
    if (json) h['Content-Type'] = 'application/json';
    if (auth) {
      final t = await Store.token();
      if (t != null) h['Authorization'] = 'Bearer $t';
    }
    return h;
  }

  static dynamic _decode(http.Response r) {
    final body = r.body.isEmpty ? {} : jsonDecode(r.body);
    if (r.statusCode >= 200 && r.statusCode < 300) return body;
    final msg = (body is Map && body['message'] is String)
        ? body['message'] as String
        : 'خطا (${r.statusCode})';
    throw ApiException(r.statusCode, msg);
  }

  static Future<dynamic> _get(String path, [Map<String, dynamic>? query]) async {
    final r = await http.get(await _uri(path, query), headers: await _headers());
    return _decode(r);
  }

  static Future<dynamic> _post(String path, Map<String, dynamic> data, {bool auth = true}) async {
    final r = await http.post(await _uri(path),
        headers: await _headers(auth: auth, json: true), body: jsonEncode(data));
    return _decode(r);
  }

  // ---- عمومی ----

  static Future<Map<String, dynamic>> appVersion() async =>
      (await _get('app-version', {'platform': platform})) as Map<String, dynamic>;

  static Future<Map<String, dynamic>> login(String identifier, String password, String device) async =>
      (await _post('$platform/login',
          {'identifier': identifier, 'password': password, 'device_name': device},
          auth: false)) as Map<String, dynamic>;

  // ---- مشترک (نیازمندِ توکن) ----

  static Future<Map<String, dynamic>> me() async =>
      (await _get('me'))['user'] as Map<String, dynamic>;

  static Future<void> logout() async {
    try {
      await _post('logout', {});
    } catch (_) {}
  }

  // ---- مخصوصِ مشتری ----

  static Future<Map<String, dynamic>> dashboard() async =>
      (await _get('portal/dashboard')) as Map<String, dynamic>;

  static Future<Map<String, dynamic>> tickets({List<String>? status, int page = 1}) async {
    final q = <String, dynamic>{'page': '$page'};
    if (status != null && status.isNotEmpty) q['status[]'] = status;
    return (await _get('portal/tickets', q)) as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> ticket(int id) async =>
      (await _get('portal/tickets/$id')) as Map<String, dynamic>;

  static Future<Map<String, dynamic>> formData() async =>
      (await _get('portal/tickets/form-data')) as Map<String, dynamic>;

  static Future<List> staff() async =>
      (await _get('portal/tickets/staff'))['staff'] as List;

  static Future<int> createTicket(Map<String, dynamic> data) async =>
      (await _post('portal/tickets', data))['id'] as int;

  static Future<void> reply(int id, String body) =>
      _post('portal/tickets/$id/reply', {'body': body});

  static Future<void> rate(int id, int rating, String? comment) =>
      _post('portal/tickets/$id/rate', {'rating': rating, 'rating_comment': comment});

  static Future<void> assign(int id, int? staffId) =>
      _post('portal/tickets/$id/assign', {'customer_assigned_to': staffId});

  static Future<Map<String, dynamic>> invoices({int page = 1}) async =>
      (await _get('portal/invoices', {'page': '$page'})) as Map<String, dynamic>;

  /// دریافتِ بایت‌های PDFِ فاکتور با توکن — برای ذخیره و بازکردن در اپ.
  static Future<Uint8List> invoicePdf(int id) async {
    final r = await http.get(await _uri('portal/invoices/$id/pdf'), headers: await _headers());
    if (r.statusCode >= 200 && r.statusCode < 300) return r.bodyBytes;
    // بدنه ممکن است JSONِ خطا باشد.
    String msg = 'خطا (${r.statusCode})';
    try {
      final j = jsonDecode(r.body);
      if (j is Map && j['message'] is String) msg = j['message'] as String;
    } catch (_) {}
    throw ApiException(r.statusCode, msg);
  }
}
