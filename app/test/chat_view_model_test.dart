import 'package:flutter_test/flutter_test.dart';
import 'package:gymtracker/features/chat/chat_view_model.dart';

import 'fake_backend.dart';

const _setsAnswer = {
  'type': 'sets',
  'exercise': 'Dip zavorrati',
  'typed_name': 'dip',
  'matched_existing_id': null,
  'weighted': true,
  'sets': [
    {'weight_kg': 45.0, 'reps': 8},
    {'weight_kg': 45.0, 'reps': 6},
  ],
};

void main() {
  late FakeBackend backend;
  late ChatViewModel vm;

  setUp(() {
    backend = FakeBackend()..on('GET /workouts/current', null);
    vm = ChatViewModel(backend.client());
  });

  Future<void> startWorkout() async {
    backend.on('POST /workouts', summaryJson());
    await vm.load();
    await vm.startWorkout();
  }

  CardItem lastCard() => vm.items.whereType<CardItem>().last;

  test('senza allenamento aperto: si mostra il pulsante per iniziare', () async {
    await vm.load();

    expect(vm.hasOpenWorkout, isFalse);
    expect(vm.items.single, isA<BotMessage>());
  });

  test('load ritrova le schede già salvate dell\'allenamento aperto', () async {
    backend.on('GET /workouts/current', summaryJson(id: 7, exercises: [
      exerciseJson(3, 'Panca piana', [[80, 5], [80, 5]]),
    ]));

    await vm.load();

    expect(vm.hasOpenWorkout, isTrue);
    final card = lastCard();
    expect(card.exercise, 'Panca piana');
    expect(card.exerciseId, 3);
    expect(card.saved, isTrue);
    expect(card.sets.length, 2);
  });

  test('una risposta con serie crea una scheda modificabile, senza salvare niente', () async {
    await startWorkout();
    backend.on('POST /parse', _setsAnswer);

    await vm.send('dip 45 per 8, poi 45x6');

    final card = lastCard();
    expect(card.saved, isFalse);
    expect(card.exercise, 'Dip zavorrati');
    expect(card.weighted, isTrue);
    expect([for (final s in card.sets) '${s.kg}×${s.reps}'], ['45×8', '45×6']);
    expect(backend.routes.where((r) => r.startsWith('PUT')), isEmpty);
  });

  test('una domanda del bot diventa solo un messaggio', () async {
    await startWorkout();
    backend.on('POST /parse', {'type': 'question', 'text': '8 sono le ripetizioni o i kg?'});

    await vm.send('squat 8 10');

    expect((vm.items.last as BotMessage).text, '8 sono le ripetizioni o i kg?');
    expect(vm.items.whereType<CardItem>(), isEmpty);
  });

  test('dopo un esercizio riconosciuto, il messaggio successivo lo manda come esercizio corrente', () async {
    await startWorkout();
    backend.on('POST /parse', {'type': 'exercise', 'exercise': 'Panca piana', 'typed_name': 'panca', 'matched_existing_id': 3, 'weighted': false});
    await vm.send('panca');

    backend.on('POST /parse', _setsAnswer);
    await vm.send('80 per 5');

    expect(backend.calls.last.body!['current_exercise'], 'Panca piana');
  });

  test('"Prossimo esercizio" azzera l\'esercizio corrente e il bot chiede quale', () async {
    await startWorkout();
    backend.on('POST /parse', {'type': 'exercise', 'exercise': 'Panca piana', 'typed_name': 'panca', 'matched_existing_id': 3, 'weighted': false});
    await vm.send('panca');

    vm.nextExercise();

    expect(vm.currentExercise, isNull);
    expect((vm.items.last as BotMessage).text, 'Che esercizio stai facendo?');
  });

  test('i numeri della scheda accettano la virgola italiana', () async {
    await startWorkout();
    backend
      ..on('POST /parse', _setsAnswer)
      ..on('POST /exercises', {'id': 5, 'name': 'Dip zavorrati', 'is_weighted_bodyweight': true})
      ..on('PUT /workouts/1/exercises/5/sets', summaryJson());
    await vm.send('dip 45 per 8, poi 45x6');

    vm.updateSet(lastCard(), 0, kg: '42,5');
    await vm.confirm(lastCard());

    expect(backend.calls.last.body!['sets'][0], {'weight_kg': 42.5, 'reps': 8});
  });

  test('un numero non valido blocca la conferma con un messaggio chiaro', () async {
    await startWorkout();
    backend.on('POST /parse', _setsAnswer);
    await vm.send('dip 45 per 8, poi 45x6');

    vm.updateSet(lastCard(), 1, reps: 'sei');
    await vm.confirm(lastCard());

    expect(vm.error, 'Serie 2: controlla kg e ripetizioni');
    expect(lastCard().saved, isFalse);
    expect(backend.routes.where((r) => r.startsWith('PUT')), isEmpty);
  });

  test('+ Serie copia l\'ultima, × toglie e rinumera', () async {
    await startWorkout();
    backend.on('POST /parse', _setsAnswer);
    await vm.send('dip');

    vm.addSet(lastCard());
    expect([for (final s in lastCard().sets) s.reps], ['8', '6', '6']);

    vm.removeSet(lastCard(), 0);
    expect([for (final s in lastCard().sets) s.reps], ['6', '6']);
  });

  test('conferma: prima crea l\'esercizio se è nuovo, poi salva le serie', () async {
    await startWorkout();
    backend
      ..on('POST /parse', _setsAnswer)
      ..on('POST /exercises', {'id': 5, 'name': 'Dip zavorrati', 'is_weighted_bodyweight': true})
      ..on('PUT /workouts/1/exercises/5/sets', summaryJson());
    await vm.send('dip 45 per 8, poi 45x6');

    await vm.confirm(lastCard());

    expect(backend.routes.skipWhile((r) => r != 'POST /exercises').toList(), ['POST /exercises', 'PUT /workouts/1/exercises/5/sets']);
    expect(backend.calls[backend.calls.length - 2].body, {'name': 'Dip zavorrati', 'is_weighted_bodyweight': true});
    expect(lastCard().saved, isTrue);
  });

  test('conferma di un esercizio riconosciuto: niente POST /exercises', () async {
    await startWorkout();
    backend
      ..on('POST /parse', {..._setsAnswer, 'matched_existing_id': 12})
      ..on('PUT /workouts/1/exercises/12/sets', summaryJson());
    await vm.send('dip 45 per 8');

    await vm.confirm(lastCard());

    expect(backend.routes, isNot(contains('POST /exercises')));
    expect(backend.routes.last, 'PUT /workouts/1/exercises/12/sets');
  });

  test('un esercizio già fatto oggi: la nuova scheda parte dalle serie già salvate', () async {
    backend
      ..on('GET /workouts/current', summaryJson(exercises: [exerciseJson(12, 'Dip zavorrati', [[45, 8]], weighted: true)]))
      ..on('POST /parse', {..._setsAnswer, 'matched_existing_id': 12, 'sets': [{'weight_kg': 40.0, 'reps': 8}]});
    await vm.load();

    await vm.send('altra serie 40 per 8');

    expect(vm.items.whereType<CardItem>().length, 1); // la scheda vecchia è sostituita
    expect([for (final s in lastCard().sets) '${s.kg}×${s.reps}'], ['45×8', '40×8']);
  });

  test('"Non è questo esercizio" usa il nome scritto come esercizio nuovo', () async {
    await startWorkout();
    backend.on('POST /parse', {..._setsAnswer, 'exercise': 'Dip zavorrati', 'typed_name': 'dip anelli', 'matched_existing_id': 12});
    await vm.send('dip anelli 45 per 8');
    expect(lastCard().canUseTypedName, isTrue);

    vm.useTypedName(lastCard());

    expect(lastCard().exercise, 'Dip anelli');
    expect(lastCard().exerciseId, isNull);
    expect(lastCard().canUseTypedName, isFalse);
  });

  test('se il backend non risponde, la scheda resta e compare il messaggio', () async {
    await startWorkout();
    backend
      ..on('POST /parse', {..._setsAnswer, 'matched_existing_id': 12})
      ..fail('PUT /workouts/1/exercises/12/sets', 503, 'Il bot non risponde, riprova tra poco');
    await vm.send('dip 45 per 8');

    await vm.confirm(lastCard());

    expect(vm.error, 'Il bot non risponde, riprova tra poco');
    expect(lastCard().saved, isFalse);
    expect(lastCard().sets.length, 2);
  });

  test('un errore del parser non perde il messaggio dell\'utente', () async {
    await startWorkout();
    backend.fail('POST /parse', 503, 'Il bot non risponde, riprova tra poco');

    await vm.send('dip 45 per 8');

    expect(vm.items.whereType<UserMessage>().last.text, 'dip 45 per 8');
    expect(vm.error, 'Il bot non risponde, riprova tra poco');
  });
}
