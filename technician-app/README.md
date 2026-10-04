# Nexora Technician

Android field application for attendance, internal tasks, notifications, and the complete HVAC service-job workflow.

## Run locally

All builds use the production API by default. Override it when developing against
a local backend:

```bash
flutter pub get
flutter run --dart-define=NEXORA_API_URL=http://10.0.2.2:8000/api/v1/
```

For a physical device, replace `10.0.2.2` with the development machine's LAN IP.

## Build

```bash
flutter analyze
flutter test
flutter build apk --release --split-per-abi
```

Builds use `https://nexora.webignitors.in/api/v1/` unless `NEXORA_API_URL` is
explicitly provided. Configure a production signing keystore before distribution.

Camera and precise location permissions are required for attendance, arrival evidence, service photos, and customer signatures. Custom Android notification audio is at `android/app/src/main/res/raw/notification_sound.mp3`.
