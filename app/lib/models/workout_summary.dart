import 'set_entry.dart';

/// Un esercizio dentro un allenamento, con le sue serie.
class WorkoutExercise {
  const WorkoutExercise({required this.id, required this.name, required this.weighted, required this.sets});

  factory WorkoutExercise.fromJson(Map<String, dynamic> json) => WorkoutExercise(
        id: json['id'] as int,
        name: json['name'] as String,
        weighted: json['is_weighted_bodyweight'] as bool,
        sets: [for (final s in json['sets'] as List) SetEntry.fromJson(s as Map<String, dynamic>)],
      );

  final int id;
  final String name;
  final bool weighted; // esercizio a zavorra: il peso si mostra col "+"
  final List<SetEntry> sets;
}

/// Il riepilogo di un allenamento, come lo restituisce il backend (contratto API "WorkoutSummary").
class WorkoutSummary {
  const WorkoutSummary({
    required this.id,
    required this.title,
    required this.startedAt,
    required this.endedAt,
    required this.durationMinutes,
    required this.setCount,
    required this.exerciseCount,
    required this.exercises,
  });

  factory WorkoutSummary.fromJson(Map<String, dynamic> json) => WorkoutSummary(
        id: json['id'] as int,
        title: json['title'] as String,
        startedAt: DateTime.parse(json['started_at'] as String).toLocal(),
        endedAt: json['ended_at'] == null ? null : DateTime.parse(json['ended_at'] as String).toLocal(),
        durationMinutes: json['duration_minutes'] as int,
        setCount: json['set_count'] as int,
        exerciseCount: json['exercise_count'] as int,
        exercises: [for (final e in json['exercises'] as List) WorkoutExercise.fromJson(e as Map<String, dynamic>)],
      );

  final int id;
  final String title;
  final DateTime startedAt;
  final DateTime? endedAt; // null = allenamento in corso
  final int durationMinutes;
  final int setCount;
  final int exerciseCount;
  final List<WorkoutExercise> exercises;
}
