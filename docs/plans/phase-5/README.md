# Phase 5: Flutter Foundation - Sub-Plans

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement these plans task-by-task.

**Goal:** Set up Flutter project with Riverpod state management, GoRouter navigation, Dio HTTP client, theme system, offline support, and authentication screens.

**Architecture:** Clean architecture with data/domain/presentation layers. Riverpod for state management. GoRouter for declarative routing. Dio with interceptors for API calls. Hive for offline caching.

**Tech Stack:** Flutter 3.x, Riverpod 2.x, GoRouter, Dio, flutter_secure_storage, Hive, connectivity_plus

**Platform Strategy:** Mobile first (iOS/Android primary), Web as fallback for desktop users. Single codebase.

**Prerequisites:** Phase 4 complete (backend API ready)

---

## Sub-Phase Structure

| Sub-Phase | Description | Tasks |
|-----------|-------------|-------|
| 5.1 | Project Setup | Tasks 1-3: Create project, dependencies, directory structure |
| 5.2 | Theme System | Task 4: Colors, typography, light/dark themes |
| 5.3 | API Client & Storage | Tasks 5, 5.5: Dio client, secure storage, Hive offline cache |
| 5.4 | Auth Provider | Task 6: User model, auth state management |
| 5.5 | Router | Task 7: GoRouter with auth redirects |
| 5.6 | Auth Screens | Tasks 8-10: Splash, login, register screens |
| 5.7 | Home & App Entry | Tasks 11-12: Home screen, main.dart, app.dart |
| 5.8 | Finalization | Tasks 13-14: Localization (EN/FR), final verification |

---

## Directory Structure

```
frontend/
├── lib/
│   ├── core/
│   │   ├── config/         # App configuration
│   │   ├── constants/      # App constants
│   │   ├── errors/         # Exception classes
│   │   ├── extensions/     # Dart extensions
│   │   ├── services/       # Core services (connectivity)
│   │   ├── theme/          # Colors, typography, themes
│   │   └── utils/          # Utility functions
│   ├── data/
│   │   ├── cache/          # Hive offline cache
│   │   ├── datasources/
│   │   │   ├── local/      # Secure storage
│   │   │   └── remote/     # API client
│   │   ├── models/         # Data models
│   │   └── repositories/   # Repository implementations
│   ├── domain/
│   │   ├── entities/       # Domain entities
│   │   └── enums/          # Domain enums
│   ├── l10n/               # Localization files
│   ├── presentation/
│   │   ├── providers/      # Riverpod providers
│   │   ├── router/         # GoRouter configuration
│   │   ├── screens/        # Screen widgets
│   │   └── widgets/        # Reusable widgets
│   ├── services/           # App services
│   ├── app.dart            # App widget
│   └── main.dart           # Entry point
├── pubspec.yaml
└── l10n.yaml
```

---

## Key Dependencies

```yaml
# State Management
flutter_riverpod: ^2.4.9
riverpod_annotation: ^2.3.3

# Navigation
go_router: ^13.0.0

# HTTP & API
dio: ^5.4.0
retrofit: ^4.0.3

# Storage
flutter_secure_storage: ^9.0.0
hive: ^2.2.3
hive_flutter: ^1.1.0

# Connectivity
connectivity_plus: ^5.0.2

# UI
google_fonts: ^6.1.0
cached_network_image: ^3.3.1
```

---

## Auth Flow

```
┌─────────────────────────────────────────────────┐
│                   App Start                      │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│              Splash Screen                       │
│         (Check auth status)                      │
└─────────────────┬───────────────────────────────┘
                  │
        ┌─────────┴─────────┐
        │                   │
        ▼                   ▼
┌───────────────┐   ┌───────────────┐
│  Has Token    │   │   No Token    │
└───────┬───────┘   └───────┬───────┘
        │                   │
        ▼                   ▼
┌───────────────┐   ┌───────────────┐
│  Fetch /me    │   │  Login Screen │
└───────┬───────┘   └───────┬───────┘
        │                   │
   ┌────┴────┐              │
   │         │              │
   ▼         ▼              ▼
┌──────┐  ┌──────┐   ┌───────────────┐
│ OK   │  │ 401  │   │  Register     │
└──┬───┘  └──┬───┘   └───────────────┘
   │         │
   ▼         ▼
┌──────┐  ┌──────────┐
│ Home │  │ Refresh  │
│Screen│  │  Token   │
└──────┘  └────┬─────┘
               │
          ┌────┴────┐
          │         │
          ▼         ▼
       ┌──────┐  ┌──────┐
       │ OK   │  │ Fail │
       │ Home │  │Login │
       └──────┘  └──────┘
```

---

## Execution Order

Execute sub-phases in order:

1. **5.1-project-setup.md** - Create Flutter project and structure
2. **5.2-theme-system.md** - Design system foundation
3. **5.3-api-client-storage.md** - Network and persistence layer
4. **5.4-auth-provider.md** - Authentication state management
5. **5.5-router.md** - Navigation with auth guards
6. **5.6-auth-screens.md** - Login and register UI
7. **5.7-home-app-entry.md** - Home screen and app bootstrap
8. **5.8-finalization.md** - Localization and final verification

---

## Reference

Main plan: `docs/plans/phase-5-flutter-foundation.md`
