import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:gymtracker/api/api_client.dart';
import 'package:gymtracker/api/api_exception.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('una risposta 200 viene decodificata dal JSON', () async {
    final api = ApiClient(
      baseUrl: 'http://api.test',
      httpClient: MockClient((request) async {
        expect(request.url.toString(), 'http://api.test/health');
        return http.Response('{"ok":true}', 200);
      }),
    );

    expect(await api.get('/health'), {'ok': true});
  });

  test('il corpo viene inviato come JSON', () async {
    final api = ApiClient(
      baseUrl: 'http://api.test',
      httpClient: MockClient((request) async {
        expect(request.method, 'POST');
        expect(request.headers['Content-Type'], startsWith('application/json'));
        expect(jsonDecode(request.body), {'text': 'dip 45 per 8'});
        return http.Response('{"type":"question","text":"?"}', 200);
      }),
    );

    await api.post('/parse', {'text': 'dip 45 per 8'});
  });

  test('un errore diventa ApiException con il messaggio del server', () async {
    final api = ApiClient(
      baseUrl: 'http://api.test',
      httpClient: MockClient((_) async => http.Response('{"error":"Allenamento non trovato"}', 404)),
    );

    expect(
      () => api.get('/workouts/9'),
      throwsA(isA<ApiException>()
          .having((e) => e.status, 'status', 404)
          .having((e) => e.message, 'message', 'Allenamento non trovato')),
    );
  });

  test('una risposta che non è JSON (es. pagina HTML di errore) diventa ApiException', () async {
    final api = ApiClient(
      baseUrl: 'http://api.test',
      httpClient: MockClient((_) async => http.Response('<html>502 Bad Gateway</html>', 502)),
    );

    expect(
      () => api.get('/health'),
      throwsA(isA<ApiException>().having((e) => e.message, 'message', 'Il server non risponde, riprova tra poco')),
    );
  });

  test('se la risposta tarda, segnala che il server si sta svegliando', () async {
    final api = ApiClient(
      baseUrl: 'http://api.test',
      slowAfter: const Duration(milliseconds: 20),
      httpClient: MockClient((_) async {
        await Future<void>.delayed(const Duration(milliseconds: 80));
        return http.Response('{"ok":true}', 200);
      }),
    );

    final call = api.get('/health');
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(api.slow.value, isTrue);

    await call;
    expect(api.slow.value, isFalse);
  });

  test('una risposta veloce non accende il segnale', () async {
    final api = ApiClient(
      baseUrl: 'http://api.test',
      slowAfter: const Duration(milliseconds: 50),
      httpClient: MockClient((_) async => http.Response('{"ok":true}', 200)),
    );

    await api.get('/health');
    await Future<void>.delayed(const Duration(milliseconds: 80));

    expect(api.slow.value, isFalse);
  });

  test('server irraggiungibile → ApiException con status 0', () async {
    final api = ApiClient(
      baseUrl: 'http://api.test',
      httpClient: MockClient((_) async => throw http.ClientException('connessione rifiutata')),
    );

    expect(() => api.get('/health'), throwsA(isA<ApiException>().having((e) => e.status, 'status', 0)));
  });
}
