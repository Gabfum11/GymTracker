#!/usr/bin/env bash
# Comando di build del sito su Render (Static Site, cartella app/).
# Render non ha Flutter: lo scarichiamo, nella stessa versione usata in locale, e compiliamo la versione web.
# API_URL (l'indirizzo dell'API su Render) si imposta tra le variabili d'ambiente del sito.
set -euo pipefail

FLUTTER_VERSION=3.47.7

if [ ! -x flutter/bin/flutter ]; then
  git clone --depth 1 --branch "$FLUTTER_VERSION" https://github.com/flutter/flutter.git
fi

flutter/bin/flutter config --no-analytics
flutter/bin/flutter build web --release --dart-define=API_URL="${API_URL:?Manca la variabile API_URL}"
