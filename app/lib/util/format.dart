/// Funzioni per mostrare e leggere i numeri all'italiana (virgola decimale).
library;

/// 42.5 → "42,5" · 45.0 → "45" · 12.25 → "12,25"
String formatKg(num kg) {
  if (kg == kg.roundToDouble()) return kg.toInt().toString();
  // al massimo due decimali (i kg vanno a passi di 0,25), senza zeri inutili in fondo
  final text = kg.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');
  return text.replaceAll('.', ',');
}

/// Una serie come la legge l'utente: "92,5 kg × 5", "+45 kg × 8" (zavorra), "12 rip" (corpo libero).
String formatSet(num kg, int reps, {bool weighted = false}) {
  if (kg == 0) return '$reps rip';
  return '${weighted ? '+' : ''}${formatKg(kg)} kg × $reps';
}

const _days = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
const _months = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

/// DateTime(2026, 10, 17) → "sab 17 ott"
String formatDay(DateTime date) => '${_days[date.weekday - 1]} ${date.day} ${_months[date.month - 1]}';

/// "42,5" o "42.5" → 42.5; tutto ciò che non è un numero → null.
double? parseItalianNumber(String text) => double.tryParse(text.trim().replaceAll(',', '.'));
