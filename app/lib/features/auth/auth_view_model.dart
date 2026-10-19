import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';

/// Le schermate del flusso di accesso (mockup: benvenuto → accesso → registrazione / codice / recupero).
enum AuthStep { welcome, login, register, code, forgot, reset }

class AuthUser {
  const AuthUser({required this.id, required this.name, required this.email});

  factory AuthUser.fromJson(Map<String, dynamic> json) =>
      AuthUser(id: json['id'] as int, name: json['name'] as String, email: json['email'] as String);

  final int id;
  final String name;
  final String email;
}

/// La logica dell'accesso, come TrackIt: access token in memoria (15 minuti),
/// refresh token salvato sul dispositivo (30 giorni), rinnovato in automatico quando l'access scade.
class AuthViewModel extends ChangeNotifier {
  AuthViewModel(this.api) {
    api.onUnauthorized = _refreshAccess;
  }

  final ApiClient api;

  static const _refreshKey = 'refresh_token';
  static const _welcomeKey = 'welcome_seen';

  bool ready = false; // false finché restore() non ha controllato se c'è una sessione salvata
  bool loggedIn = false;
  AuthStep step = AuthStep.login;
  AuthUser? user;
  bool busy = false;
  String? error;
  String? info; // messaggi positivi, es. "Password cambiata"

  /// L'email a cui abbiamo mandato il codice, e a cosa serve il codice.
  String? pendingEmail;
  String _codePurpose = 'verify'; // 'verify' = verifica email, 'reset' = recupero password
  String? _resetToken;
  Future<String?>? _refreshing;

  bool get codeIsForReset => _codePurpose == 'reset';

  /// All'avvio: benvenuto la prima volta; se c'è un refresh token salvato si entra senza password.
  Future<void> restore() async {
    final prefs = await SharedPreferences.getInstance();
    step = (prefs.getBool(_welcomeKey) ?? false) ? AuthStep.login : AuthStep.welcome;
    if (prefs.getString(_refreshKey) != null) await _refreshAccess();
    ready = true;
    notifyListeners();
  }

  Future<void> continueFromWelcome() async {
    await (await SharedPreferences.getInstance()).setBool(_welcomeKey, true);
    goTo(AuthStep.login);
  }

  void goTo(AuthStep next) {
    step = next;
    error = null;
    info = null;
    notifyListeners();
  }

  Future<void> login(String email, String password) => _run(() async {
        email = _clean(email);
        try {
          await _openSession(await api.post('/auth/login', {'email': email, 'password': password}));
        } on ApiException catch (e) {
          if (e.status != 403) rethrow;
          // email non ancora verificata: il codice di prima può essere scaduto, ne mandiamo uno nuovo
          await api.post('/auth/resend-code', {'email': email});
          _askCode(email, 'verify');
        }
      });

  Future<void> register(String name, String email, String password) => _run(() async {
        email = _clean(email);
        _checkPassword(password);
        if (name.trim().isEmpty) throw const ApiException(400, 'Scrivi il tuo nome');
        await api.post('/auth/register', {'name': name.trim(), 'email': email, 'password': password});
        _askCode(email, 'verify');
      });

  Future<void> forgotPassword(String email) => _run(() async {
        email = _clean(email);
        await api.post('/auth/forgot-password', {'email': email});
        _askCode(email, 'reset');
      });

  Future<void> submitCode(String code) => _run(() async {
        final body = {'email': pendingEmail, 'code': code.trim()};
        if (codeIsForReset) {
          _resetToken = (await api.post('/auth/reset-code', body) as Map<String, dynamic>)['reset_token'] as String;
          step = AuthStep.reset;
        } else {
          await _openSession(await api.post('/auth/verify-email', body));
        }
      });

  Future<void> resendCode() => _run(() async {
        await api.post(codeIsForReset ? '/auth/forgot-password' : '/auth/resend-code', {'email': pendingEmail});
        info = 'Codice inviato di nuovo';
      });

  Future<void> resetPassword(String password) => _run(() async {
        _checkPassword(password);
        await api.post('/auth/reset-password', {'reset_token': _resetToken, 'password': password});
        _resetToken = null;
        step = AuthStep.login;
        info = 'Password cambiata. Ora accedi.';
      });

  /// Esci da questo dispositivo. Si cancella tutto anche se il server non risponde.
  Future<void> logout() async {
    final refresh = (await SharedPreferences.getInstance()).getString(_refreshKey);
    try {
      if (refresh != null) await api.post('/auth/logout', {'refresh_token': refresh});
    } on ApiException {
      // il token resterà valido sul server fino alla scadenza: sul dispositivo comunque non c'è più
    }
    await _clear();
  }

  /// Esci da tutti i dispositivi (il server invalida ogni sessione).
  Future<void> logoutAll() => _run(() async {
        await api.post('/auth/logout-all');
        await _clear();
      });

  /// Rinnova l'access token. Se arrivano più 401 insieme, il rinnovo si fa UNA volta sola:
  /// un secondo rinnovo userebbe un refresh già consumato e il server chiuderebbe tutte le sessioni.
  Future<String?> _refreshAccess() => _refreshing ??= _doRefresh().whenComplete(() => _refreshing = null);

  Future<String?> _doRefresh() async {
    final refresh = (await SharedPreferences.getInstance()).getString(_refreshKey);
    if (refresh == null) return null;
    try {
      await _openSession(await api.post('/auth/refresh', {'refresh_token': refresh}));
      return api.token;
    } on ApiException {
      await _clear();
      return null;
    }
  }

  Future<void> _openSession(dynamic json) async {
    final session = json as Map<String, dynamic>;
    api.token = session['access_token'] as String;
    await (await SharedPreferences.getInstance()).setString(_refreshKey, session['refresh_token'] as String);
    user = AuthUser.fromJson(session['user'] as Map<String, dynamic>);
    loggedIn = true;
  }

  Future<void> _clear() async {
    api.token = null;
    await (await SharedPreferences.getInstance()).remove(_refreshKey);
    user = null;
    loggedIn = false;
    step = AuthStep.login;
    notifyListeners();
  }

  void _askCode(String email, String purpose) {
    pendingEmail = email;
    _codePurpose = purpose;
    step = AuthStep.code;
  }

  static String _clean(String email) => email.trim().toLowerCase();

  static void _checkPassword(String password) {
    if (password.length < 8) throw const ApiException(400, 'La password deve avere almeno 8 caratteri');
  }

  /// Come in ChatViewModel: "sto caricando" ed errori gestiti in un posto solo.
  Future<void> _run(Future<void> Function() action) async {
    busy = true;
    error = null;
    info = null;
    notifyListeners();
    try {
      await action();
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      busy = false;
      notifyListeners();
    }
  }
}
