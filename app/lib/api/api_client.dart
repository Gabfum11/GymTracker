import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_exception.dart';

/// L'indirizzo del backend. In locale è il server PHP; per la versione online si passa
/// con `flutter build web --dart-define=API_URL=https://...`.
const apiUrl = String.fromEnvironment('API_URL', defaultValue: 'http://localhost:8080');

/// L'unico punto dell'app che fa richieste HTTP al backend.
/// Restituisce il JSON già decodificato (Map, List o null) oppure lancia [ApiException].
class ApiClient {
  ApiClient({required this.baseUrl, this.token, http.Client? httpClient})
      : _http = httpClient ?? http.Client();

  final String baseUrl;

  /// Il token di login (dal passo 9); null = non ancora autenticato.
  String? token;

  final http.Client _http;

  Future<dynamic> get(String path) => _send('GET', path);

  Future<dynamic> post(String path, [Object? body]) => _send('POST', path, body);

  Future<dynamic> put(String path, [Object? body]) => _send('PUT', path, body);

  Future<dynamic> _send(String method, String path, [Object? body]) async {
    final request = http.Request(method, Uri.parse('$baseUrl$path'))
      ..headers['Accept'] = 'application/json';
    if (token != null) request.headers['Authorization'] = 'Bearer $token';
    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }

    final http.Response response;
    try {
      response = await http.Response.fromStream(await _http.send(request));
    } on http.ClientException {
      throw const ApiException(0, 'Server non raggiungibile. Controlla la connessione.');
    }

    // utf8.decode: senza, le lettere accentate ("è") arriverebbero rovinate
    final text = utf8.decode(response.bodyBytes);
    final data = text.isEmpty ? null : jsonDecode(text);
    if (response.statusCode >= 400) {
      final message = data is Map && data['error'] is String ? data['error'] as String : 'Errore ${response.statusCode}';
      throw ApiException(response.statusCode, message);
    }
    return data;
  }
}
