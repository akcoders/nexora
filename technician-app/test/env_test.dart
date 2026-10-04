import 'package:flutter_test/flutter_test.dart';
import 'package:nexora_technician/core/config/env.dart';

void main() {
  test('production API URL is HTTPS and versioned', () {
    expect(Env.prodUrl, startsWith('https://'));
    expect(Env.prodUrl, endsWith('/api/v1/'));
  });
}
