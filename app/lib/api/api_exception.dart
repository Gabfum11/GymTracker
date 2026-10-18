/// Una chiamata al backend non è andata a buon fine.
/// [status] è il codice HTTP (404, 503…), 0 se il server non è raggiungibile.
/// [message] è già in italiano e si può mostrare all'utente.
class ApiException implements Exception {
  const ApiException(this.status, this.message);

  final int status;
  final String message;

  @override
  String toString() => 'ApiException($status): $message';
}
