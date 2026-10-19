import 'dart:convert';

import 'package:gymtracker/api/api_client.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

/// Un backend finto per i test: per ogni "METODO /percorso" dici cosa rispondere,
/// e lui si ricorda tutte le chiamate ricevute (in ordine), con il corpo JSON.
class FakeBackend {
  final Map<String, Object? Function(Map<String, dynamic>? body)> _routes = {};
  final List<({String route, Map<String, dynamic>? body, String? auth})> calls = [];

  /// Risponde 200 con [answer] (oppure con il risultato della funzione, se è una funzione).
  void on(String route, Object? answer) =>
      _routes[route] = answer is Function ? (b) => (answer as dynamic)(b) : (_) => answer;

  /// Risponde con un errore HTTP.
  void fail(String route, int status, String message) =>
      _routes[route] = (_) => HttpError(status, message);

  List<String> get routes => [for (final c in calls) c.route];

  ApiClient client() => ApiClient(
        baseUrl: 'http://api.test',
        httpClient: MockClient((request) async {
          final route = '${request.method} ${request.url.path}';
          final body = request.body.isEmpty ? null : jsonDecode(request.body) as Map<String, dynamic>;
          calls.add((route: route, body: body, auth: request.headers['Authorization']));
          final handler = _routes[route];
          if (handler == null) return http.Response('{"error":"rotta non prevista nel test: $route"}', 404);
          final answer = handler(body);
          // .bytes + utf8: come il server vero, così "giù" o "è" arrivano intatti
          if (answer is HttpError) return http.Response.bytes(utf8.encode(jsonEncode({'error': answer.message})), answer.status);
          return http.Response.bytes(utf8.encode(jsonEncode(answer)), 200);
        }),
      );
}

class HttpError {
  const HttpError(this.status, this.message);
  final int status;
  final String message;
}

/// Un WorkoutSummary minimo, come lo manda il backend.
Map<String, dynamic> summaryJson({int id = 1, List<Map<String, dynamic>> exercises = const []}) => {
      'id': id,
      'title': 'Allenamento',
      'started_at': '2026-10-18T10:00:00+00:00',
      'ended_at': null,
      'duration_minutes': 5,
      'set_count': exercises.fold<int>(0, (n, e) => n + (e['sets'] as List).length),
      'exercise_count': exercises.length,
      'records': [],
      'exercises': exercises,
    };

Map<String, dynamic> exerciseJson(int id, String name, List<List<num>> sets, {bool weighted = false}) => {
      'id': id,
      'name': name,
      'is_weighted_bodyweight': weighted,
      'sets': [for (final s in sets) {'weight_kg': s[0].toDouble(), 'reps': s[1], 'is_record': false}],
    };
