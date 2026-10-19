import 'package:flutter/foundation.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../models/parse_result.dart';
import '../../models/set_entry.dart';
import '../../models/workout_summary.dart';
import '../../util/format.dart';

/// Un elemento della chat: un messaggio del bot, uno dell'utente o una scheda di conferma.
sealed class ChatItem {}

class BotMessage extends ChatItem {
  BotMessage(this.text);
  final String text;
}

class UserMessage extends ChatItem {
  UserMessage(this.text);
  final String text;
}

/// Una serie mentre l'utente la modifica: testo, così "42," a metà digitazione non dà errore.
class SetDraft {
  SetDraft(this.kg, this.reps);
  factory SetDraft.from(SetEntry s) => SetDraft(formatKg(s.weightKg), '${s.reps}');

  String kg;
  String reps;

  SetDraft copy() => SetDraft(kg, reps);
}

/// La scheda di conferma: esercizio + serie modificabili. Si salva solo con "Conferma".
class CardItem extends ChatItem {
  CardItem({
    required this.exercise,
    required this.typedName,
    required this.exerciseId,
    required this.weighted,
    required this.sets,
    this.saved = false,
  });

  String exercise;
  final String typedName; // come l'ha scritto l'utente, per "Non è questo esercizio"
  int? exerciseId; // null = esercizio nuovo, da creare alla conferma
  bool weighted;
  final List<SetDraft> sets;
  bool saved;

  /// Cambia quando si aggiungono o tolgono righe: serve alla schermata per ricreare i campi.
  int revision = 0;

  /// L'AI ha riconosciuto un esercizio esistente con un nome diverso da quello scritto.
  bool get canUseTypedName =>
      !saved && exerciseId != null && typedName.toLowerCase() != exercise.toLowerCase();
}

/// La logica della chat (la "V M" di MVVM): la schermata la legge e chiama i suoi metodi,
/// e quando qualcosa cambia notifyListeners() le dice di ridisegnarsi.
class ChatViewModel extends ChangeNotifier {
  ChatViewModel(this._api);

  final ApiClient _api;

  final List<ChatItem> items = [];
  int? workoutId;
  String? currentExercise;
  bool busy = false;
  String? error;

  bool get hasOpenWorkout => workoutId != null;

  /// All'apertura: ritrova l'allenamento in corso (se c'è) con le schede già salvate.
  Future<void> load() => _run(() async {
        items.clear();
        final json = await _api.get('/workouts/current');
        if (json == null) {
          workoutId = null;
          items.add(BotMessage('Ciao! Premi «Inizia allenamento», poi scrivi gli esercizi come ti viene, '
              'per esempio «dip 45 per 8, poi 45 per 6».'));
          return;
        }
        final workout = WorkoutSummary.fromJson(json as Map<String, dynamic>);
        workoutId = workout.id;
        items.add(BotMessage('Allenamento in corso. Continua da dove eri rimasto.'));
        for (final e in workout.exercises) {
          items.add(CardItem(
            exercise: e.name,
            typedName: e.name,
            exerciseId: e.id,
            weighted: e.weighted,
            sets: [for (final s in e.sets) SetDraft.from(s)],
            saved: true,
          ));
        }
        currentExercise = workout.exercises.isEmpty ? null : workout.exercises.last.name;
      });

  Future<void> startWorkout() => _run(() async {
        final workout = WorkoutSummary.fromJson(await _api.post('/workouts') as Map<String, dynamic>);
        workoutId = workout.id;
        items.add(BotMessage('Allenamento iniziato! Che esercizio stai facendo?'));
      });

  void nextExercise() {
    currentExercise = null;
    items.add(BotMessage('Che esercizio stai facendo?'));
    notifyListeners();
  }

  Future<void> send(String text) async {
    text = text.trim();
    if (text.isEmpty) return;
    items.add(UserMessage(text));
    await _run(() async {
      final json = await _api.post('/parse', {
        'text': text,
        if (currentExercise != null) 'current_exercise': currentExercise,
      });
      switch (ParseResult.fromJson(json as Map<String, dynamic>)) {
        case ParsedQuestion(:final text):
          items.add(BotMessage(text));
        case ParsedSets(:final exercise, :final sets):
          _addCard(exercise, sets);
        case final ParsedExercise exercise:
          currentExercise = exercise.name;
          items.add(BotMessage('${exercise.name}: scrivi le serie come ti viene, anche tutte insieme.'));
      }
    });
  }

  /// Crea la scheda. Se l'esercizio è già nell'allenamento, la scheda riparte dalle serie salvate.
  void _addCard(ParsedExercise exercise, List<SetEntry> sets) {
    currentExercise = exercise.name;
    final previous = items.whereType<CardItem>().where((c) => c.saved && _sameExercise(c, exercise)).firstOrNull;
    if (previous != null) items.remove(previous);
    items.add(CardItem(
      exercise: exercise.name,
      typedName: exercise.typedName,
      exerciseId: exercise.matchedId ?? previous?.exerciseId,
      weighted: exercise.weighted,
      sets: [...?previous?.sets, for (final s in sets) SetDraft.from(s)],
    ));
  }

  bool _sameExercise(CardItem card, ParsedExercise exercise) =>
      (exercise.matchedId != null && card.exerciseId == exercise.matchedId) ||
      card.exercise.toLowerCase() == exercise.name.toLowerCase();

  void updateSet(CardItem card, int index, {String? kg, String? reps}) {
    if (kg != null) card.sets[index].kg = kg;
    if (reps != null) card.sets[index].reps = reps;
    // niente notifyListeners: il campo di testo mostra già quello che l'utente scrive
  }

  void addSet(CardItem card) {
    card.sets.add(card.sets.isEmpty ? SetDraft('', '') : card.sets.last.copy());
    card.revision++;
    notifyListeners();
  }

  void removeSet(CardItem card, int index) {
    card.sets.removeAt(index);
    card.revision++;
    notifyListeners();
  }

  void reopen(CardItem card) {
    card.saved = false;
    notifyListeners();
  }

  /// "Non è questo esercizio": l'AI aveva proposto un esercizio esistente, l'utente ne vuole uno nuovo.
  void useTypedName(CardItem card) {
    final name = card.typedName.trim();
    card.exercise = name.isEmpty ? name : name[0].toUpperCase() + name.substring(1);
    card.exerciseId = null;
    notifyListeners();
  }

  Future<void> confirm(CardItem card) async {
    final sets = <Map<String, dynamic>>[];
    for (final (i, draft) in card.sets.indexed) {
      final kg = parseItalianNumber(draft.kg);
      final reps = int.tryParse(draft.reps.trim());
      if (kg == null || reps == null) {
        error = 'Serie ${i + 1}: controlla kg e ripetizioni';
        notifyListeners();
        return;
      }
      sets.add({'weight_kg': kg, 'reps': reps});
    }

    await _run(() async {
      card.exerciseId ??= ((await _api.post('/exercises', {
        'name': card.exercise,
        'is_weighted_bodyweight': card.weighted,
      })) as Map<String, dynamic>)['id'] as int;
      await _api.put('/workouts/$workoutId/exercises/${card.exerciseId}/sets', {'sets': sets});
      card.saved = true;
      currentExercise = card.exercise;
    });
  }

  /// Il riepilogo dell'allenamento in corso (con i record), per la schermata di fine allenamento.
  Future<WorkoutSummary?> currentSummary() async {
    WorkoutSummary? summary;
    await _run(() async {
      final json = await _api.get('/workouts/current');
      summary = json == null ? null : WorkoutSummary.fromJson(json as Map<String, dynamic>);
    });
    return summary;
  }

  /// "Salva allenamento": lo chiude e la chat riparte da capo.
  Future<void> finish() async {
    await _run(() => _api.post('/workouts/$workoutId/finish'));
    if (error == null) await load();
  }

  /// "Annulla allenamento": lo cancella con tutte le serie e la chat riparte da capo.
  Future<void> cancel() async {
    await _run(() => _api.delete('/workouts/$workoutId'));
    if (error == null) await load();
  }

  /// Esegue una chiamata al backend gestendo "sto caricando" e gli errori in un posto solo.
  Future<void> _run(Future<void> Function() action) async {
    busy = true;
    error = null;
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
