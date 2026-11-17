String? canonicalCarStatus(String? value) {
  if (value == null) return null;
  final key = value.trim().toLowerCase().replaceAll(RegExp(r'[ -]'), '_');
  return switch (key) {
    'open' || 'in_progress' || 'closed' || 'awaiting_verification' || 'returned_for_revision' => key,
    'inprogress' => 'in_progress',
    'returned' || 'needs_revision' => 'returned_for_revision',
    _ => value,
  };
}
