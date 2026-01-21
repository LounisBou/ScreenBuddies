# ScreenBuddies - Frontend Architecture

## Overview

Cross-platform mobile and web application built with Flutter, using modern state management and clean architecture principles.

**Platform Strategy:** Mobile first (iOS/Android), Web as fallback for desktop users. Single codebase.

---

## Technology Stack

| Component | Technology | Version |
|-----------|------------|---------|
| Framework | Flutter | 3.x (latest stable) |
| Language | Dart | 3.x |
| State Management | Riverpod | 2.x |
| Navigation | GoRouter | Latest |
| HTTP Client | Dio | Latest |
| Local Storage | SharedPreferences | Latest |
| Push Notifications | Firebase Cloud Messaging | Latest |
| Internationalization | flutter_localizations + intl | Built-in |

---

## Target Platforms

| Platform | Priority | Notes |
|----------|----------|-------|
| Android | Primary | Min SDK 21 (Android 5.0) |
| iOS | Primary | Min iOS 12 |
| Web | Fallback | Modern browsers, desktop users only |

> **Note:** Web is a fallback for desktop users who receive invite links. Mobile apps are the primary experience. No SEO requirements.

---

## Directory Structure

```
lib/
├── main.dart                          # App entry point
├── app.dart                           # MaterialApp configuration
│
├── core/
│   ├── config/
│   │   ├── app_config.dart           # Environment config
│   │   ├── api_config.dart           # API endpoints
│   │   └── theme_config.dart         # Theme settings
│   │
│   ├── constants/
│   │   ├── app_constants.dart        # Static values
│   │   ├── asset_paths.dart          # Asset references
│   │   └── route_paths.dart          # Route constants
│   │
│   ├── errors/
│   │   ├── app_exception.dart        # Custom exceptions
│   │   ├── api_exception.dart        # API error handling
│   │   └── failure.dart              # Failure classes
│   │
│   ├── extensions/
│   │   ├── context_extensions.dart   # BuildContext helpers
│   │   ├── string_extensions.dart
│   │   └── datetime_extensions.dart
│   │
│   ├── utils/
│   │   ├── validators.dart           # Form validation
│   │   ├── formatters.dart           # Date, number formatting
│   │   └── logger.dart               # Logging utility
│   │
│   └── theme/
│       ├── app_theme.dart            # Theme definitions
│       ├── app_colors.dart           # Color palette
│       └── app_typography.dart       # Text styles
│
├── data/
│   ├── datasources/
│   │   ├── remote/
│   │   │   ├── api_client.dart       # Dio configuration
│   │   │   ├── auth_api.dart
│   │   │   ├── election_api.dart
│   │   │   ├── candidate_api.dart
│   │   │   ├── voting_api.dart         # GET /vote/next, POST /vote
│   │   │   ├── user_api.dart
│   │   │   └── media_search_api.dart
│   │   │
│   │   └── local/
│   │       ├── secure_storage.dart   # Token storage
│   │       └── preferences.dart      # App settings
│   │
│   ├── models/
│   │   ├── user_model.dart
│   │   ├── user_preference_model.dart
│   │   ├── election_model.dart
│   │   ├── candidate_model.dart
│   │   ├── duel_model.dart
│   │   ├── voter_model.dart
│   │   ├── friendship_model.dart
│   │   ├── media_item_model.dart
│   │   └── api_response.dart
│   │
│   └── repositories/
│       ├── auth_repository.dart
│       ├── user_repository.dart
│       ├── election_repository.dart
│       ├── candidate_repository.dart
│       ├── voting_repository.dart
│       ├── friendship_repository.dart
│       └── media_search_repository.dart
│
├── domain/
│   ├── entities/
│   │   ├── user.dart
│   │   ├── election.dart
│   │   ├── candidate.dart
│   │   ├── duel.dart
│   │   ├── voter.dart
│   │   └── friendship.dart
│   │
│   └── enums/
│       ├── election_status.dart
│       ├── election_type.dart
│       └── friendship_status.dart
│
├── presentation/
│   ├── providers/
│   │   ├── auth_provider.dart
│   │   ├── user_provider.dart
│   │   ├── election_provider.dart
│   │   ├── elections_list_provider.dart
│   │   ├── voting_provider.dart        # Current duel state, vote action
│   │   ├── candidates_provider.dart
│   │   ├── media_search_provider.dart
│   │   ├── friendship_provider.dart
│   │   └── locale_provider.dart
│   │
│   ├── router/
│   │   ├── app_router.dart           # GoRouter configuration
│   │   ├── route_guards.dart         # Auth guards
│   │   └── route_transitions.dart    # Custom transitions
│   │
│   ├── screens/
│   │   ├── splash/
│   │   │   └── splash_screen.dart
│   │   │
│   │   ├── auth/
│   │   │   ├── login_screen.dart
│   │   │   ├── register_screen.dart
│   │   │   ├── forgot_password_screen.dart
│   │   │   ├── reset_password_screen.dart
│   │   │   └── verify_email_screen.dart
│   │   │
│   │   ├── home/
│   │   │   └── home_screen.dart      # Elections list
│   │   │
│   │   ├── election/
│   │   │   ├── create/
│   │   │   │   ├── create_election_screen.dart
│   │   │   │   ├── select_type_step.dart
│   │   │   │   ├── election_details_step.dart
│   │   │   │   ├── add_candidates_step.dart
│   │   │   │   └── review_step.dart
│   │   │   │
│   │   │   ├── detail/
│   │   │   │   ├── election_detail_screen.dart
│   │   │   │   ├── candidates_tab.dart
│   │   │   │   ├── voters_tab.dart
│   │   │   │   └── results_tab.dart
│   │   │   │
│   │   │   ├── share/
│   │   │   │   └── share_election_screen.dart
│   │   │   │
│   │   │   └── join/
│   │   │       └── join_election_screen.dart
│   │   │
│   │   ├── voting/
│   │   │   ├── duel_screen.dart      # Main voting interface
│   │   │   └── voting_complete_screen.dart
│   │   │
│   │   ├── media_search/
│   │   │   └── media_search_screen.dart
│   │   │
│   │   ├── friends/
│   │   │   ├── friends_list_screen.dart
│   │   │   └── friend_requests_screen.dart
│   │   │
│   │   ├── profile/
│   │   │   ├── profile_screen.dart
│   │   │   ├── edit_profile_screen.dart
│   │   │   └── notification_settings_screen.dart
│   │   │
│   │   └── settings/
│   │       └── settings_screen.dart
│   │
│   └── widgets/
│       ├── common/
│       │   ├── app_button.dart
│       │   ├── app_text_field.dart
│       │   ├── app_card.dart
│       │   ├── loading_indicator.dart
│       │   ├── error_view.dart
│       │   └── empty_state.dart
│       │
│       ├── election/
│       │   ├── election_card.dart
│       │   ├── election_status_badge.dart
│       │   ├── candidate_card.dart
│       │   └── voter_avatar.dart
│       │
│       ├── duel/
│       │   ├── duel_card.dart        # Single candidate in duel
│       │   ├── vs_divider.dart
│       │   └── progress_indicator.dart
│       │
│       └── media/
│           ├── media_search_result.dart
│           └── media_detail_sheet.dart
│
├── l10n/
│   ├── app_en.arb                    # English strings
│   └── app_fr.arb                    # French strings
│
└── services/
    ├── notification_service.dart     # Push notification handling
    ├── deep_link_service.dart        # Deep link handling
    └── analytics_service.dart        # Event tracking (future)
```

---

## State Management with Riverpod

### Provider Types

```dart
// Auth state - global, persisted
@riverpod
class Auth extends _$Auth {
  @override
  FutureOr<User?> build() async {
    return _loadCurrentUser();
  }

  Future<void> login(String email, String password) async { ... }
  Future<void> logout() async { ... }
}

// Election detail - parameterized by ID
@riverpod
Future<Election> election(ElectionRef ref, String electionId) async {
  final repository = ref.read(electionRepositoryProvider);
  return repository.getById(electionId);
}

// Current voting state (next duel to vote on)
@riverpod
class Voting extends _$Voting {
  @override
  FutureOr<Duel?> build(String electionId) async {
    return _fetchNextDuel(electionId);  // Calls GET /elections/{id}/vote/next
  }

  Future<void> vote(String winnerId) async { ... }  // Calls POST /elections/{id}/vote
}

// Elections list with filtering
@riverpod
class ElectionsList extends _$ElectionsList {
  @override
  FutureOr<List<Election>> build() async {
    return _fetchMyElections();
  }

  Future<void> refresh() async { ... }
}
```

### Provider Organization

| Provider | Scope | Purpose |
|----------|-------|---------|
| authProvider | Global | Current user, tokens |
| localeProvider | Global | App language |
| electionsListProvider | Global | User's elections |
| electionProvider(id) | Per election | Single election details |
| candidatesProvider(id) | Per election | Election candidates |
| votingProvider(id) | Per election | Active duel for voting |
| mediaSearchProvider | Ephemeral | Search results |

---

## Navigation with GoRouter

### Route Structure

```dart
final router = GoRouter(
  initialLocation: '/splash',
  redirect: _authRedirect,
  routes: [
    GoRoute(path: '/splash', builder: (_, __) => SplashScreen()),

    // Auth routes (unauthenticated)
    GoRoute(path: '/login', builder: (_, __) => LoginScreen()),
    GoRoute(path: '/register', builder: (_, __) => RegisterScreen()),
    GoRoute(path: '/forgot-password', builder: (_, __) => ForgotPasswordScreen()),
    GoRoute(path: '/reset-password/:token', builder: ...),
    GoRoute(path: '/verify-email/:token', builder: ...),

    // Main app (authenticated)
    ShellRoute(
      builder: (_, __, child) => AppShell(child: child),
      routes: [
        GoRoute(path: '/', builder: (_, __) => HomeScreen()),
        GoRoute(path: '/friends', builder: (_, __) => FriendsListScreen()),
        GoRoute(path: '/profile', builder: (_, __) => ProfileScreen()),
        GoRoute(path: '/settings', builder: (_, __) => SettingsScreen()),
      ],
    ),

    // Election routes
    GoRoute(path: '/election/create', builder: (_, __) => CreateElectionScreen()),
    GoRoute(path: '/election/:id', builder: ...),
    GoRoute(path: '/election/:id/vote', builder: ...),
    GoRoute(path: '/election/:id/share', builder: ...),

    // Invite link entry point
    GoRoute(path: '/join/:token', builder: ...),
  ],
);
```

### Deep Linking

| URL Pattern | Action |
|-------------|--------|
| `/join/{token}` | Join election via invite link |
| `/verify-email/{token}` | Verify email address |
| `/reset-password/{token}` | Reset password |

---

## API Client Configuration

```dart
class ApiClient {
  late final Dio _dio;

  ApiClient(Ref ref) {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: Duration(seconds: 30),
      receiveTimeout: Duration(seconds: 30),
    ));

    // Request interceptor - add auth token
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await ref.read(authProvider.notifier).getAccessToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        options.headers['Accept-Language'] = ref.read(localeProvider);
        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // Try refresh token
          final refreshed = await ref.read(authProvider.notifier).refreshToken();
          if (refreshed) {
            // Retry original request
            return handler.resolve(await _retry(error.requestOptions));
          }
        }
        handler.next(error);
      },
    ));
  }
}
```

---

## Screen Flows

### Election Creation Flow

```
┌──────────────┐
│ Select Type  │ Movie / TV Show / Video Game / Board Game / Theater
└──────┬───────┘
       │
       ▼
┌──────────────┐
│   Details    │ Title, description, dates, K value
└──────┬───────┘
       │
       ▼
┌──────────────┐
│  Candidates  │ Search & add from external APIs
└──────┬───────┘
       │
       ▼
┌──────────────┐
│   Campaign   │ Enable suggestions? Auto-approve?
│  (Optional)  │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│   Review     │ Summary, confirm creation
└──────┬───────┘
       │
       ▼
┌──────────────┐
│    Share     │ Copy/share join link
└──────────────┘
```

### Voting Flow

```
┌──────────────┐
│ Election     │ See candidates, status, voters
│   Detail     │
└──────┬───────┘
       │ "Start Voting"
       ▼
┌──────────────┐     ┌──────────────┐
│    Duel      │────►│    Duel      │ (repeat)
│  A vs B      │     │  C vs D      │
└──────┬───────┘     └──────────────┘
       │
       │ (all duels complete OR election ended)
       ▼
┌──────────────┐
│   Results    │ Winners + stats
└──────────────┘
```

---

## Duel Screen UI

```
┌─────────────────────────────────────┐
│  Election Title            [12/45]  │ ← Progress
├─────────────────────────────────────┤
│                                     │
│  ┌─────────────────────────────┐   │
│  │                             │   │
│  │      [Movie Poster A]       │   │
│  │                             │   │
│  │      Movie Title A          │   │
│  │      2024 • Action          │   │
│  │                             │   │
│  └─────────────────────────────┘   │
│                                     │
│              ── VS ──               │
│                                     │
│  ┌─────────────────────────────┐   │
│  │                             │   │
│  │      [Movie Poster B]       │   │
│  │                             │   │
│  │      Movie Title B          │   │
│  │      2023 • Drama           │   │
│  │                             │   │
│  └─────────────────────────────┘   │
│                                     │
│          [ Skip this duel ]         │ ← Skip button
│                                     │
└─────────────────────────────────────┘
   Tap a card to choose, or skip
```

---

## Localization

### String Organization

```arb
// app_en.arb
{
  "appTitle": "ScreenBuddies",
  "election_status_voting": "Voting",
  "election_status_ended": "Ended",
  "duel_choose_favorite": "Choose your favorite",
  "duel_skip": "Skip this duel",
  "duel_skip_hint": "Don't know either? Skip it.",
  "duel_progress": "{completed} of {total} duels",
  "@duel_progress": {
    "placeholders": {
      "completed": {"type": "int"},
      "total": {"type": "int"}
    }
  }
}
```

### Usage

```dart
Text(context.l10n.duel_progress(12, 45))
// → "12 of 45 duels"
```

---

## Error Handling

### UI Error States

```dart
class AsyncValueWidget<T> extends StatelessWidget {
  final AsyncValue<T> value;
  final Widget Function(T) data;

  @override
  Widget build(BuildContext context) {
    return value.when(
      data: data,
      loading: () => LoadingIndicator(),
      error: (error, stack) => ErrorView(
        message: _getErrorMessage(error),
        onRetry: () => /* refresh provider */,
      ),
    );
  }
}
```

### Error Messages

| Error Code | User Message (EN) |
|------------|-------------------|
| NETWORK_ERROR | Unable to connect. Check your internet. |
| ELECTION_CLOSED | This election has ended. |
| UNAUTHORIZED | Please log in again. |
| NOT_FOUND | Election not found. |

---

## Push Notifications

### Notification Types

| Type | Action |
|------|--------|
| deadline_reminder | Open election detail |
| election_ended | Open results screen |
| friendship_request | Open friend requests |

### Handling

```dart
class NotificationService {
  void handleNotification(RemoteMessage message) {
    final type = message.data['type'];
    final targetId = message.data['target_id'];

    switch (type) {
      case 'election_ended':
        router.go('/election/$targetId');
        break;
      case 'deadline_reminder':
        router.go('/election/$targetId');
        break;
      case 'friendship_request':
        router.go('/friends/requests');
        break;
    }
  }
}
```

---

## Testing Strategy

### Unit Tests
- Providers: state transitions, error handling
- Repositories: API mapping
- Utilities: validators, formatters

### Widget Tests
- Individual screens and components
- Form validation UI
- Loading/error states

### Integration Tests
- Complete flows (login → create election → vote)
- Deep link handling
- Offline behavior (error states)

---

## Performance Considerations

| Optimization | Implementation |
|--------------|----------------|
| Image caching | cached_network_image package |
| List virtualization | ListView.builder for all lists |
| Lazy loading | Load election details on demand |
| State preservation | Keep alive for frequently accessed screens |
| Bundle size | Deferred loading for web |
