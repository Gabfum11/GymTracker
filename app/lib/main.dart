import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'shell.dart';
import 'theme.dart';

void main() {
  runApp(GymTrackerApp(api: ApiClient(baseUrl: apiUrl)));
}

class GymTrackerApp extends StatelessWidget {
  const GymTrackerApp({super.key, required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'GymTracker',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      home: Shell(api: api),
    );
  }
}
