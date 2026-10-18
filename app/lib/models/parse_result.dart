import 'set_entry.dart';

/// Cosa ha capito il bot da un messaggio (POST /parse). Tre casi possibili:
/// - [ParsedSets]: esercizio + serie → diventa una scheda di conferma
/// - [ParsedExercise]: solo l'esercizio → il bot chiede le serie
/// - [ParsedQuestion]: non era chiaro → il bot fa una domanda
sealed class ParseResult {
  const ParseResult();

  factory ParseResult.fromJson(Map<String, dynamic> json) => switch (json['type']) {
        'sets' => ParsedSets(
            exercise: ParsedExercise.fromJson(json),
            sets: [for (final s in json['sets'] as List) SetEntry.fromJson(s as Map<String, dynamic>)],
          ),
        'exercise' => ParsedExercise.fromJson(json),
        _ => ParsedQuestion(json['text'] as String? ?? 'Non ho capito, puoi riscriverlo?'),
      };
}

class ParsedExercise extends ParseResult {
  const ParsedExercise({required this.name, required this.typedName, required this.matchedId, required this.weighted});

  factory ParsedExercise.fromJson(Map<String, dynamic> json) => ParsedExercise(
        name: json['exercise'] as String,
        typedName: json['typed_name'] as String,
        matchedId: json['matched_existing_id'] as int?,
        weighted: json['weighted'] as bool,
      );

  final String name; // il nome da usare (quello salvato, se l'esercizio è stato riconosciuto)
  final String typedName; // come l'ha scritto l'utente
  final int? matchedId; // id dell'esercizio esistente riconosciuto, o null se è nuovo
  final bool weighted;
}

class ParsedSets extends ParseResult {
  const ParsedSets({required this.exercise, required this.sets});

  final ParsedExercise exercise;
  final List<SetEntry> sets;
}

class ParsedQuestion extends ParseResult {
  const ParsedQuestion(this.text);

  final String text;
}
