# Flutter — in-app update check (Android APK)

Uses existing stack: **Dio + GetIt + package_info_plus + url_launcher + shared_preferences**.

Backend endpoints:

- `GET /api/v1/app/update?version_code={buildNumber}&locale=ar|en`
- Download page: `/app`
- Direct APK: `/app/download`

## Project layout

```text
lib/core/update/
  app_update_info.dart
  app_update_service.dart
  app_update_checker.dart
  app_update_dialog.dart
  app_update_preferences.dart

lib/api/services/
  app_update_api_service.dart
```

Register in GetIt (example):

```dart
getIt.registerLazySingleton<AppUpdateApiService>(
  () => AppUpdateApiService(getIt<Dio>()),
);
getIt.registerLazySingleton<AppUpdateService>(
  () => AppUpdateService(getIt<AppUpdateApiService>()),
);
getIt.registerLazySingleton<AppUpdatePreferences>(
  () => AppUpdatePreferences(getIt<SharedPreferences>()),
);
getIt.registerLazySingleton<AppUpdateChecker>(
  () => AppUpdateChecker(
    getIt<AppUpdateService>(),
    getIt<AppUpdatePreferences>(),
  ),
);
```

---

## 1. Model — `lib/core/update/app_update_info.dart`

```dart
class AppUpdateInfo {
  const AppUpdateInfo({
    required this.updateAvailable,
    required this.latestVersion,
    required this.latestVersionCode,
    required this.downloadUrl,
    required this.pageUrl,
    required this.forceUpdate,
    required this.notes,
    this.publishedAt,
    this.sha256,
    this.fileSize,
  });

  final bool updateAvailable;
  final String? latestVersion;
  final int? latestVersionCode;
  final String? downloadUrl;
  final String pageUrl;
  final bool forceUpdate;
  final String? notes;
  final String? publishedAt;
  final String? sha256;
  final int? fileSize;

  factory AppUpdateInfo.fromJson(Map<String, dynamic> json) {
    return AppUpdateInfo(
      updateAvailable: json['update_available'] == true,
      latestVersion: json['latest_version'] as String?,
      latestVersionCode: json['latest_version_code'] as int?,
      downloadUrl: json['download_url'] as String?,
      pageUrl: json['page_url'] as String? ?? '',
      forceUpdate: json['force_update'] == true,
      notes: json['notes'] as String?,
      publishedAt: json['published_at'] as String?,
      sha256: json['sha256'] as String?,
      fileSize: json['file_size'] as int?,
    );
  }

  static const empty = AppUpdateInfo(
    updateAvailable: false,
    latestVersion: null,
    latestVersionCode: null,
    downloadUrl: null,
    pageUrl: '',
    forceUpdate: false,
    notes: null,
  );
}
```

---

## 2. API service — `lib/api/services/app_update_api_service.dart`

```dart
import 'package:dio/dio.dart';

import '../../core/update/app_update_info.dart';

class AppUpdateApiService {
  AppUpdateApiService(this._dio);

  final Dio _dio;

  Future<AppUpdateInfo> fetchUpdate({
    required int versionCode,
    required String locale,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/app/update',
      queryParameters: {
        'version_code': versionCode,
        'locale': locale,
      },
    );

    final body = response.data;
    if (body == null || body['success'] != true) {
      return AppUpdateInfo.empty;
    }

    final data = body['data'];
    if (data is! Map<String, dynamic>) {
      return AppUpdateInfo.empty;
    }

    return AppUpdateInfo.fromJson(data);
  }
}
```

> Dio base URL should already point to `{PASSENGER_API_ORIGIN}/api/v1`.

---

## 3. Service — `lib/core/update/app_update_service.dart`

```dart
import 'package:package_info_plus/package_info_plus.dart';

import '../../api/services/app_update_api_service.dart';
import 'app_update_info.dart';

class AppUpdateService {
  AppUpdateService(this._api);

  final AppUpdateApiService _api;

  Future<int> currentVersionCode() async {
    final info = await PackageInfo.fromPlatform();
    return int.tryParse(info.buildNumber) ?? 0;
  }

  Future<String> currentVersionLabel() async {
    final info = await PackageInfo.fromPlatform();
    return info.version;
  }

  Future<AppUpdateInfo> checkForUpdate({String locale = 'ar'}) async {
    final versionCode = await currentVersionCode();

    return _api.fetchUpdate(
      versionCode: versionCode,
      locale: locale,
    );
  }
}
```

---

## 4. Preferences — `lib/core/update/app_update_preferences.dart`

Stores when the user tapped **Later** so the same release is not shown again until a newer one appears.

```dart
import 'package:shared_preferences/shared_preferences.dart';

class AppUpdatePreferences {
  AppUpdatePreferences(this._prefs);

  static const _dismissedVersionCodeKey = 'app_update.dismissed_version_code';
  static const _lastCheckAtKey = 'app_update.last_check_at';

  final SharedPreferences _prefs;

  int? dismissedVersionCode() {
    final value = _prefs.getInt(_dismissedVersionCodeKey);
    return value == null || value <= 0 ? null : value;
  }

  Future<void> setDismissedVersionCode(int versionCode) {
    return _prefs.setInt(_dismissedVersionCodeKey, versionCode);
  }

  DateTime? lastCheckAt() {
    final raw = _prefs.getString(_lastCheckAtKey);
    return raw == null ? null : DateTime.tryParse(raw);
  }

  Future<void> setLastCheckAt(DateTime value) {
    return _prefs.setString(_lastCheckAtKey, value.toUtc().toIso8601String());
  }
}
```

---

## 5. Dialog — `lib/core/update/app_update_dialog.dart`

```dart
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import 'app_update_info.dart';

class AppUpdateDialog extends StatelessWidget {
  const AppUpdateDialog({
    super.key,
    required this.update,
    required this.isArabic,
  });

  final AppUpdateInfo update;
  final bool isArabic;

  Future<void> _openDownload(BuildContext context) async {
    final url = update.downloadUrl ?? update.pageUrl;
    final uri = Uri.parse(url);

    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isArabic ? 'تعذر فتح رابط التحميل' : 'Could not open download link',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = isArabic ? 'تحديث متاح' : 'Update available';
    final versionLabel = update.latestVersion ?? '';
    final message = update.notes?.trim().isNotEmpty == true
        ? update.notes!.trim()
        : (isArabic
            ? 'يتوفر إصدار جديد ($versionLabel). حمّل التحديث للمتابعة.'
            : 'A new version ($versionLabel) is available. Download the update to continue.');
    final downloadLabel = isArabic ? 'تحميل التحديث' : 'Download update';
    final laterLabel = isArabic ? 'لاحقاً' : 'Later';

    return PopScope(
      canPop: !update.forceUpdate,
      child: AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          if (!update.forceUpdate)
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: Text(laterLabel),
            ),
          FilledButton(
            onPressed: () async {
              await _openDownload(context);
              if (!update.forceUpdate && context.mounted) {
                Navigator.of(context).pop(true);
              }
            },
            child: Text(downloadLabel),
          ),
        ],
      ),
    );
  }
}
```

---

## 6. Checker — `lib/core/update/app_update_checker.dart`

Supports:

- check on launch
- periodic check every 24 hours
- manual check from settings

```dart
import 'dart:io';

import 'package:flutter/material.dart';

import 'app_update_dialog.dart';
import 'app_update_info.dart';
import 'app_update_preferences.dart';
import 'app_update_service.dart';

enum AppUpdateCheckSource { launch, periodic, manual }

class AppUpdateChecker {
  AppUpdateChecker(this._service, this._preferences);

  static const periodicInterval = Duration(hours: 24);

  final AppUpdateService _service;
  final AppUpdatePreferences _preferences;

  Future<void> checkOnLaunch(
    BuildContext context, {
    required bool isArabic,
  }) {
    return _check(
      context,
      isArabic: isArabic,
      source: AppUpdateCheckSource.launch,
    );
  }

  Future<void> checkPeriodicallyIfDue(
    BuildContext context, {
    required bool isArabic,
  }) async {
    final lastCheck = _preferences.lastCheckAt();
    final now = DateTime.now();

    if (lastCheck != null && now.difference(lastCheck) < periodicInterval) {
      return;
    }

    await _check(
      context,
      isArabic: isArabic,
      source: AppUpdateCheckSource.periodic,
    );
  }

  Future<bool> checkManually(
    BuildContext context, {
    required bool isArabic,
  }) async {
    return _check(
      context,
      isArabic: isArabic,
      source: AppUpdateCheckSource.manual,
      showNoUpdateFeedback: true,
    );
  }

  Future<bool> _check(
    BuildContext context, {
    required bool isArabic,
    required AppUpdateCheckSource source,
    bool showNoUpdateFeedback = false,
  }) async {
    if (!Platform.isAndroid) {
      return false;
    }

    try {
      final update = await _service.checkForUpdate(
        locale: isArabic ? 'ar' : 'en',
      );

      await _preferences.setLastCheckAt(DateTime.now());

      if (!context.mounted) {
        return false;
      }

      if (!update.updateAvailable) {
        if (showNoUpdateFeedback) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                isArabic
                    ? 'أنت تستخدم أحدث إصدار'
                    : 'You are on the latest version',
              ),
            ),
          );
        }
        return false;
      }

      if (!_shouldShowDialog(update, source)) {
        return false;
      }

      final accepted = await showDialog<bool>(
        context: context,
        barrierDismissible: !update.forceUpdate,
        builder: (_) => AppUpdateDialog(
          update: update,
          isArabic: isArabic,
        ),
      );

      if (accepted != true &&
          !update.forceUpdate &&
          update.latestVersionCode != null) {
        await _preferences.setDismissedVersionCode(update.latestVersionCode!);
      }

      return true;
    } catch (_) {
      if (showNoUpdateFeedback && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              isArabic
                  ? 'تعذر التحقق من التحديث'
                  : 'Could not check for updates',
            ),
          ),
        );
      }
      return false;
    }
  }

  bool _shouldShowDialog(AppUpdateInfo update, AppUpdateCheckSource source) {
    if (update.forceUpdate) {
      return true;
    }

    if (source == AppUpdateCheckSource.manual) {
      return true;
    }

    final dismissedCode = _preferences.dismissedVersionCode();
    final latestCode = update.latestVersionCode;

    if (dismissedCode == null || latestCode == null) {
      return true;
    }

    return latestCode > dismissedCode;
  }
}
```

---

## 7. Wire it in

### On launch (splash/home)

```dart
WidgetsBinding.instance.addPostFrameCallback((_) async {
  final isArabic = Localizations.localeOf(context).languageCode == 'ar';
  final checker = getIt<AppUpdateChecker>();

  await checker.checkOnLaunch(context, isArabic: isArabic);
  await checker.checkPeriodicallyIfDue(context, isArabic: isArabic);
});
```

### Settings screen button

```dart
ListTile(
  leading: const Icon(Icons.system_update),
  title: Text(isArabic ? 'التحقق من التحديث' : 'Check for updates'),
  onTap: () => getIt<AppUpdateChecker>().checkManually(
    context,
    isArabic: isArabic,
  ),
)
```

---

## 8. Version alignment

```yaml
# pubspec.yaml
version: 1.2.0+120
```

Server APK:

```text
storage/app/releases/booke-1.2.0+120.apk
```

The number after `+` must match on both sides.

---

## 9. Local test

1. Keep app at `version: 1.0.0+100`.
2. Put `storage/app/releases/booke-1.2.0+120.apk` on Laravel.
3. Run against local API:

```bash
flutter run --dart-define=PASSENGER_API_ORIGIN=http://10.0.2.2:8000
```

`10.0.2.2` = localhost from Android emulator.

4. Open app → update dialog should appear.

---

## 10. Behaviour summary

| Case | Behaviour |
|------|-----------|
| `force_update: false` | Shows **Later**; same release is not shown again after Later |
| `force_update: true` | Dialog cannot be dismissed until download |
| Network failure | App opens normally; manual check shows error snackbar |
| iOS / Web | Skipped — use App Store |
| Periodic check | Runs at most once every 24 hours |
| Manual check | Always checks; shows snackbar if already up to date |

---

## 11. Server workflow (each release)

1. `flutter build apk --release`
2. Rename to `booke-1.2.0+120.apk`
3. Upload to `storage/app/releases/`
4. Optional: update `release.json` for notes / `force_update`
5. Do **not** edit `.env` — server auto-detects latest APK

---

## 12. Expected API response

```json
{
  "success": true,
  "message": "App update status fetched successfully.",
  "data": {
    "update_available": true,
    "latest_version": "1.2.0",
    "latest_version_code": 120,
    "download_url": "https://booke.ly/app/download",
    "page_url": "https://booke.ly/app",
    "force_update": false,
    "notes": "إصلاحات وتحسينات",
    "published_at": "2026-08-23T12:00:00+00:00",
    "sha256": "...",
    "file_size": 12345678
  }
}
```
