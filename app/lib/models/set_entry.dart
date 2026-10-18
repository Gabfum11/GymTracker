/// Una serie: kg × ripetizioni. [isRecord] arriva dal backend (passo 8).
class SetEntry {
  const SetEntry({required this.weightKg, required this.reps, this.isRecord = false});

  factory SetEntry.fromJson(Map<String, dynamic> json) => SetEntry(
        weightKg: (json['weight_kg'] as num).toDouble(),
        reps: json['reps'] as int,
        isRecord: json['is_record'] as bool? ?? false,
      );

  final double weightKg;
  final int reps;
  final bool isRecord;

  Map<String, dynamic> toJson() => {'weight_kg': weightKg, 'reps': reps};
}
