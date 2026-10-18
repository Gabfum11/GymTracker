import 'package:flutter_test/flutter_test.dart';
import 'package:gymtracker/util/format.dart';

void main() {
  group('formatKg', () {
    test('usa la virgola e toglie ",0"', () {
      expect(formatKg(42.5), '42,5');
      expect(formatKg(45), '45');
      expect(formatKg(45.0), '45');
      expect(formatKg(12.25), '12,25');
    });
  });

  group('formatSet', () {
    test('peso normale', () {
      expect(formatSet(92.5, 5), '92,5 kg × 5');
    });

    test('esercizio a zavorra: il peso ha il "+"', () {
      expect(formatSet(45, 8, weighted: true), '+45 kg × 8');
    });

    test('corpo libero senza peso: solo le ripetizioni', () {
      expect(formatSet(0, 12), '12 rip');
    });
  });

  group('parseItalianNumber', () {
    test('accetta virgola e punto', () {
      expect(parseItalianNumber('42,5'), 42.5);
      expect(parseItalianNumber('42.5'), 42.5);
      expect(parseItalianNumber(' 8 '), 8);
    });

    test('testo non numerico → null', () {
      expect(parseItalianNumber('x'), isNull);
      expect(parseItalianNumber(''), isNull);
    });
  });
}
