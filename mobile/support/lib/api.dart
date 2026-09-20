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

/// کلاینتِ API — همهٔ درخواست‌ها با توکنِ Bearer و آدرسِ سرورِ ذخیره‌شده.
/// بدنهٔ POST به‌صورتِ JSON فرستاده می‌شود تا آرایه‌ها (مثلِ روش‌های انجام) درست بروند.
class Api {
  static const platform = 'support';

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

  /// POSTِ چندبخشی (multipart) برای آپلودِ فایل. فیلدها رشته‌اند و فایل‌ها با
  /// نامِ «attachments[]» می‌روند تا Laravel آن‌ها را آرایه ببیند.
  static Future<dynamic> _multipart(String path, Map<String, String> fields, List<String> filePaths) async {
    final req = http.MultipartRequest('POST', await _uri(path));
    req.headers.addAll(await _headers()); // Accept + Authorization (بدونِ Content-Type — خودش می‌گذارد)
    req.fields.addAll(fields);
    for (final p in filePaths) {
      req.files.add(await http.MultipartFile.fromPath('attachments[]', p));
    }
    final streamed = await req.send();
    return _decode(await http.Response.fromStream(streamed));
  }

  // ---- عمومی ----

  static Future<Map<String, dynamic>> appVersion() async =>
      (await _get('app-version', {'platform': platform})) as Map<String, dynamic>;

  static Future<Map<String, dynamic>> login(String identifier, String password, String device) async =>
      (await _post('$platform/login',
          {'identifier': identifier, 'password': password, 'device_name': device},
          auth: false)) as Map<String, dynamic>;

  // ---- نیازمندِ توکن ----

  static Future<Map<String, dynamic>> me() async =>
      (await _get('me'))['user'] as Map<String, dynamic>;

  static Future<void> logout() async {
    try {
      await _post('logout', {});
    } catch (_) {}
  }

  static Future<Map<String, dynamic>> dashboard() async =>
      (await _get('dashboard')) as Map<String, dynamic>;

  static Future<Map<String, dynamic>> tickets({List<String>? status, String? search, String? priority, int page = 1}) async {
    final q = <String, dynamic>{'page': '$page'};
    if (status != null && status.isNotEmpty) q['status[]'] = status;
    if (search != null && search.isNotEmpty) q['search'] = search;
    if (priority != null && priority.isNotEmpty) q['priority'] = priority;
    return (await _get('tickets', q)) as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> ticket(int id) async =>
      (await _get('tickets/$id')) as Map<String, dynamic>;

  static Future<Map<String, dynamic>> meta() async =>
      (await _get('tickets/meta')) as Map<String, dynamic>;

  /// دادهٔ فرمِ ساختِ تیکت. با دادنِ customerId، پروژه‌های همان مشتری هم می‌آید.
  static Future<Map<String, dynamic>> formData({int? customerId}) async =>
      (await _get('tickets/form-data', customerId != null ? {'customer_id': '$customerId'} : null))
          as Map<String, dynamic>;

  static Future<void> reply(int id, String body, int workMinutes,
      {bool internal = false, List<String> files = const []}) async {
    if (files.isEmpty) {
      await _post('tickets/$id/reply', {'body': body, 'work_minutes': workMinutes, 'is_internal': internal});
    } else {
      await _multipart('tickets/$id/reply',
          {'body': body, 'work_minutes': '$workMinutes', 'is_internal': internal ? '1' : '0'}, files);
    }
  }

  static Future<void> resolve(int id, List<String> methods, String? resolution) =>
      _post('tickets/$id/resolve', {'method': methods, 'resolution': resolution});

  static Future<void> changeStatus(int id, String status, {List<String>? methods, String? resolution}) =>
      _post('tickets/$id/status', {
        'status': status,
        if (resolution != null) 'resolution': resolution,
        if (methods != null && methods.isNotEmpty) 'method': methods,
      });

  static Future<int> createTicket(Map<String, dynamic> data, {List<String> files = const []}) async {
    if (files.isEmpty) {
      return (await _post('tickets', data))['id'] as int;
    }
    final fields = <String, String>{};
    data.forEach((k, v) { if (v != null) fields[k] = '$v'; });
    return (await _multipart('tickets', fields, files))['id'] as int;
  }

  static Future<void> assign(int id, int? staffId) =>
      _post('tickets/$id/assign', {'assigned_to': staffId});

  static Future<void> resetRating(int id) => _post('tickets/$id/reset-rating', {});

  static Future<List> staff() async =>
      (await _get('tickets/staff'))['staff'] as List;

  // ---- فاکتورها ----

  static Future<Map<String, dynamic>> invoices({String? search, List<String>? status, int page = 1}) async {
    final q = <String, dynamic>{'page': '$page'};
    if (search != null && search.isNotEmpty) q['search'] = search;
    if (status != null && status.isNotEmpty) q['status[]'] = status;
    return (await _get('invoices', q)) as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> invoice(int id) async =>
      (await _get('invoices/$id')) as Map<String, dynamic>;

  static Future<Map<String, dynamic>> payInvoice(int id, int amount, String method, {String? reference}) async =>
      (await _post('invoices/$id/pay', {
        'amount': amount, 'method': method,
        if (reference != null && reference.isNotEmpty) 'reference': reference,
      })) as Map<String, dynamic>;

  // ---- مشتریان ----

  static Future<Map<String, dynamic>> customers({String? search, int page = 1}) async {
    final q = <String, dynamic>{'page': '$page'};
    if (search != null && search.isNotEmpty) q['search'] = search;
    return (await _get('customers', q)) as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> customer(int id) async =>
      (await _get('customers/$id')) as Map<String, dynamic>;

  /// بایت‌های PDFِ فاکتور با توکن — برای ذخیره و بازکردن در اپ.
  static Future<Uint8List> invoicePdf(int id) async {
    final r = await http.get(await _uri('invoices/$id/pdf'), headers: await _headers());
    if (r.statusCode >= 200 && r.statusCode < 300) return r.bodyBytes;
    String msg = 'خطا (${r.statusCode})';
    try {
      final j = jsonDecode(r.body);
      if (j is Map && j['message'] is String) msg = j['message'] as String;
    } catch (_) {}
    throw ApiException(r.statusCode, msg);
  }
}
