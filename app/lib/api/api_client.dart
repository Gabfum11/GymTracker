import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import 'api_exception.dart';

/// L'indirizzo del backend. In locale è il server PHP; per la versione online si passa
/// con `flutter build web --dart-define=API_URL=https://...`.
const apiUrl = String.fromEnvironment('API_URL', defaultValue: 'http://localhost:8080');

/// L'unico punto dell'app che fa richieste HTTP al backend.
/// Restituisce il JSON già decodificato (Map, List o null) oppure lancia [ApiException].
class ApiClient {
  ApiClient({
    required this.baseUrl,
    this.token,
    http.Client? httpClient,
    this.slowAfter = const Duration(seconds: 4),
  }) : _http = httpClient ?? http.Client();

  final String baseUrl;

  /// L'access token (JWT, 15 minuti); null = non autenticato.
  String? token;

  /// Chiamato quando il server risponde 401: deve procurare un access token nuovo
  /// (con il refresh token) e restituirlo, oppure null se la sessione è finita. Lo imposta AuthViewModel.
  Future<String?> Function()? onUnauthorized;

  final http.Client _http;

  /// Diventa true quando una richiesta impiega più di [slowAfter]: su Render gratuito
  /// il server dorme dopo 15 minuti e la prima risposta può arrivare dopo ~1 minuto.
  final ValueNotifier<bool> slow = ValueNotifier(false);
  final Duration slowAfter;
  int _slowRequests = 0;

  Future<dynamic> get(String path) => _send('GET', path);

  Future<dynamic> post(String path, [Object? body]) => _send('POST', path, body);

  Future<dynamic> put(String path, [Object? body]) => _send('PUT', path, body);

  Future<dynamic> delete(String path) => _send('DELETE', path);

  Future<dynamic> _send(String method, String path, [Object? body, bool retried = false]) async {
    final request = http.Request(method, Uri.parse('$baseUrl$path'))
      ..headers['Accept'] = 'application/json';
    if (token != null) request.headers['Authorization'] = 'Bearer $token';
    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }

    final http.Response response;
    final timer = Timer(slowAfter, () {
      _slowRequests++;
      slow.value = true;
    });
    try {
      response = await http.Response.fromStream(await _http.send(request));
    } on http.ClientException {
      throw const ApiException(0, 'Server non raggiungibile. Controlla la connessione.');
    } finally {
      if (!timer.isActive) _slowRequests--; // il timer era scattato: questa richiesta era "lenta"
      timer.cancel();
      slow.value = _slowRequests > 0;
    }

    // access token scaduto: se ne chiede uno nuovo e si riprova UNA volta (le rotte /auth/ no, per non girare in tondo)
    if (response.statusCode == 401 && !retried && !path.startsWith('/auth/') && onUnauthorized != null) {
      if (await onUnauthorized!() != null) return _send(method, path, body, true);
    }

    final Object? data;
    try {
      // utf8.decode: senza, le lettere accentate ("è") arriverebbero rovinate
      final text = utf8.decode(response.bodyBytes);
      data = text.isEmpty ? null : jsonDecode(text);
    } on FormatException {
      // non è il nostro JSON: di solito una pagina di errore del proxy (es. Render che si sta svegliando)
      throw ApiException(response.statusCode, 'Il server non risponde, riprova tra poco');
    }
    if (response.statusCode >= 400) {
      final message = data is Map && data['error'] is String ? data['error'] as String : 'Errore ${response.statusCode}';
      throw ApiException(response.statusCode, message);
    }
    return data;
  }
}
