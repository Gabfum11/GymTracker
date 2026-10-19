import 'package:flutter_test/flutter_test.dart';
import 'package:gymtracker/features/auth/auth_view_model.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'fake_backend.dart';

Map<String, dynamic> session([String n = '1']) => {
      'access_token': 'access-$n',
      'refresh_token': 'refresh-$n',
      'user': {'id': 7, 'name': 'Mario', 'email': 'mario@test.it'},
    };

void main() {
  late FakeBackend backend;
  late AuthViewModel auth;

  Future<void> start([Map<String, Object> saved = const {'welcome_seen': true}]) async {
    SharedPreferences.setMockInitialValues(saved);
    backend = FakeBackend();
    auth = AuthViewModel(backend.client());
  }

  Future<String?> savedRefresh() async => (await SharedPreferences.getInstance()).getString('refresh_token');

  group('avvio', () {
    test('la prima volta si vede il benvenuto, poi non più', () async {
      await start(const {});
      await auth.restore();
      expect(auth.step, AuthStep.welcome);

      await auth.continueFromWelcome();
      expect(auth.step, AuthStep.login);
      expect((await SharedPreferences.getInstance()).getBool('welcome_seen'), isTrue);
    });

    test('con un refresh token salvato si entra senza password', () async {
      await start({'welcome_seen': true, 'refresh_token': 'refresh-0'});
      backend.on('POST /auth/refresh', session());

      await auth.restore();

      expect(auth.loggedIn, isTrue);
      expect(auth.user?.name, 'Mario');
      expect(backend.calls.single.body, {'refresh_token': 'refresh-0'});
      expect(await savedRefresh(), 'refresh-1'); // ruotato: si salva quello nuovo
    });

    test('se il refresh salvato non vale più si torna all\'accesso', () async {
      await start({'welcome_seen': true, 'refresh_token': 'vecchio'});
      backend.fail('POST /auth/refresh', 401, 'Sessione scaduta, accedi di nuovo');

      await auth.restore();

      expect(auth.loggedIn, isFalse);
      expect(auth.step, AuthStep.login);
      expect(await savedRefresh(), isNull);
    });
  });

  group('accesso', () {
    test('login giusto: entra e salva il refresh token', () async {
      await start();
      backend.on('POST /auth/login', session());

      await auth.login('Mario@Test.it ', 'password123');

      expect(auth.loggedIn, isTrue);
      expect(backend.calls.single.body, {'email': 'mario@test.it', 'password': 'password123'});
      expect(await savedRefresh(), 'refresh-1');
    });

    test('credenziali sbagliate: messaggio del server', () async {
      await start();
      backend.fail('POST /auth/login', 401, 'Email o password errati');

      await auth.login('mario@test.it', 'sbagliata');

      expect(auth.loggedIn, isFalse);
      expect(auth.error, 'Email o password errati');
    });

    test('email non verificata: manda un codice nuovo e passa alla schermata del codice', () async {
      await start();
      backend
        ..fail('POST /auth/login', 403, 'Verifica prima la tua email')
        ..on('POST /auth/resend-code', null);

      await auth.login('mario@test.it', 'password123');

      expect(auth.step, AuthStep.code);
      expect(auth.pendingEmail, 'mario@test.it');
      expect(backend.routes.last, 'POST /auth/resend-code');
      expect(auth.error, isNull);
    });
  });

  test('registrazione → codice → dentro', () async {
    await start();
    backend
      ..on('POST /auth/register', {'message': 'ok'})
      ..on('POST /auth/verify-email', session());

    await auth.register('Mario', 'mario@test.it', 'password123');
    expect(auth.step, AuthStep.code);

    await auth.submitCode('123456');

    expect(backend.calls.last.body, {'email': 'mario@test.it', 'code': '123456'});
    expect(auth.loggedIn, isTrue);
  });

  test('password dimenticata → codice → nuova password → di nuovo all\'accesso', () async {
    await start();
    backend
      ..on('POST /auth/forgot-password', null)
      ..on('POST /auth/reset-code', {'reset_token': 'reset-jwt'})
      ..on('POST /auth/reset-password', null);

    await auth.forgotPassword('mario@test.it');
    expect(auth.step, AuthStep.code);
    await auth.submitCode('654321');
    expect(auth.step, AuthStep.reset);
    await auth.resetPassword('nuovapass1');

    expect(backend.calls.last.body, {'reset_token': 'reset-jwt', 'password': 'nuovapass1'});
    expect(auth.step, AuthStep.login);
    expect(auth.info, 'Password cambiata. Ora accedi.');
    expect(auth.loggedIn, isFalse);
  });

  test('una password troppo corta non parte nemmeno', () async {
    await start();

    await auth.register('Mario', 'mario@test.it', 'corta');

    expect(auth.error, 'La password deve avere almeno 8 caratteri');
    expect(backend.calls, isEmpty);
  });

  test('esci: cancella tutto anche se il server non risponde', () async {
    await start();
    backend
      ..on('POST /auth/login', session())
      ..fail('POST /auth/logout', 503, 'giù');
    await auth.login('mario@test.it', 'password123');

    await auth.logout();

    expect(auth.loggedIn, isFalse);
    expect(auth.step, AuthStep.login);
    expect(await savedRefresh(), isNull);
  });

  group('token scaduto', () {
    test('si rinnova da solo una volta e la richiesta riparte con il token nuovo', () async {
      await start();
      var calls = 0;
      backend
        ..on('POST /auth/login', session('1'))
        ..on('POST /auth/refresh', session('2'))
        ..on('GET /me', (_) => ++calls == 1 ? const HttpError(401, 'Accesso richiesto') : {'id': 7});
      await auth.login('mario@test.it', 'password123');

      final me = await auth.api.get('/me');

      expect(me, {'id': 7});
      expect(backend.routes, ['POST /auth/login', 'GET /me', 'POST /auth/refresh', 'GET /me']);
      expect(backend.calls.last.auth, 'Bearer access-2');
      expect(await savedRefresh(), 'refresh-2');
    });

    test('due richieste scadute insieme fanno un solo rinnovo', () async {
      await start();
      final seen = <String>{};
      backend
        ..on('POST /auth/login', session('1'))
        ..on('POST /auth/refresh', session('2'))
        ..on('GET /me', (_) => seen.add('me') ? const HttpError(401, 'x') : {'id': 7})
        ..on('GET /exercises', (_) => seen.add('ex') ? const HttpError(401, 'x') : []);
      await auth.login('mario@test.it', 'password123');

      await Future.wait([auth.api.get('/me'), auth.api.get('/exercises')]);

      // il secondo rinnovo userebbe un refresh già consumato: il server chiuderebbe tutte le sessioni
      expect(backend.routes.where((r) => r == 'POST /auth/refresh').length, 1);
    });

    test('se anche il rinnovo fallisce si esce', () async {
      await start();
      backend
        ..on('POST /auth/login', session())
        ..fail('POST /auth/refresh', 401, 'Sessione non valida')
        ..fail('GET /me', 401, 'Accesso richiesto');
      await auth.login('mario@test.it', 'password123');

      await expectLater(auth.api.get('/me'), throwsA(anything));

      expect(auth.loggedIn, isFalse);
      expect(auth.step, AuthStep.login);
    });
  });
}
