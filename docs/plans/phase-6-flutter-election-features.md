# Phase 6: Flutter Election Features

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement election list, creation flow, detail view, voting/duel screen, and results display.

**Architecture:** Feature-based organization with providers per feature. Reusable widgets for elections and candidates. Multi-step creation flow with stepper.

**Tech Stack:** Flutter 3.x, Riverpod 2.x, GoRouter, Dio

**Platform Strategy:** Mobile first (iOS/Android primary), Web as fallback for desktop users.

**Prerequisites:** Phase 5 complete (Flutter foundation with auth)

---

## Task 1: Create Election Models

**Files:**
- Create: `frontend/lib/data/models/election_model.dart`
- Create: `frontend/lib/data/models/candidate_model.dart`
- Create: `frontend/lib/data/models/media_type_model.dart`

**Step 1: Create MediaType model**

Create `frontend/lib/data/models/media_type_model.dart`:
```dart
import 'package:equatable/equatable.dart';

class MediaType extends Equatable {
  final String code;
  final String label;

  const MediaType({
    required this.code,
    required this.label,
  });

  factory MediaType.fromJson(Map<String, dynamic> json) {
    return MediaType(
      code: json['code'] as String,
      label: json['label'] as String,
    );
  }

  @override
  List<Object?> get props => [code, label];
}
```

**Step 2: Create Candidate model**

Create `frontend/lib/data/models/candidate_model.dart`:
```dart
import 'package:equatable/equatable.dart';

class Candidate extends Equatable {
  final int id;
  final String externalId;
  final String title;
  final String? posterUrl;
  final int? year;
  final Map<String, dynamic>? metadata;
  final bool isApproved;

  const Candidate({
    required this.id,
    required this.externalId,
    required this.title,
    this.posterUrl,
    this.year,
    this.metadata,
    this.isApproved = true,
  });

  factory Candidate.fromJson(Map<String, dynamic> json) {
    return Candidate(
      id: json['id'] as int,
      externalId: json['external_id'] as String,
      title: json['title'] as String,
      posterUrl: json['poster_url'] as String?,
      year: json['year'] as int?,
      metadata: json['metadata'] as Map<String, dynamic>?,
      isApproved: json['is_approved'] as bool? ?? true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'external_id': externalId,
      'title': title,
      'poster_url': posterUrl,
      'year': year,
      'metadata': metadata,
    };
  }

  @override
  List<Object?> get props => [id, externalId, title];
}

class MediaItem extends Equatable {
  final String externalId;
  final String title;
  final String? posterUrl;
  final int? year;
  final Map<String, dynamic>? metadata;

  const MediaItem({
    required this.externalId,
    required this.title,
    this.posterUrl,
    this.year,
    this.metadata,
  });

  factory MediaItem.fromJson(Map<String, dynamic> json) {
    return MediaItem(
      externalId: json['external_id'] as String,
      title: json['title'] as String,
      posterUrl: json['poster_url'] as String?,
      year: json['year'] as int?,
      metadata: json['metadata'] as Map<String, dynamic>?,
    );
  }

  Candidate toCandidate() {
    return Candidate(
      id: 0, // Will be assigned by backend
      externalId: externalId,
      title: title,
      posterUrl: posterUrl,
      year: year,
      metadata: metadata,
    );
  }

  @override
  List<Object?> get props => [externalId, title];
}
```

**Step 3: Create Election model**

Create `frontend/lib/data/models/election_model.dart`:
```dart
import 'package:equatable/equatable.dart';
import 'candidate_model.dart';
import 'media_type_model.dart';

enum ElectionStatus {
  draft,
  campaign,
  voting,
  ended,
  archived;

  static ElectionStatus fromString(String value) {
    return ElectionStatus.values.firstWhere(
      (e) => e.name == value,
      orElse: () => ElectionStatus.voting,
    );
  }
}

class Election extends Equatable {
  final int id;
  final String uuid;
  final String title;
  final String? description;
  final MediaType mediaType;
  final ElectionStatus status;
  final bool isMaestro;
  final DateTime electionDate;
  final DateTime deadline;
  final DateTime? campaignEnd;
  final int winnerCount;
  final int candidateCount;
  final int voterCount;
  final DateTime createdAt;

  const Election({
    required this.id,
    required this.uuid,
    required this.title,
    this.description,
    required this.mediaType,
    required this.status,
    required this.isMaestro,
    required this.electionDate,
    required this.deadline,
    this.campaignEnd,
    required this.winnerCount,
    required this.candidateCount,
    required this.voterCount,
    required this.createdAt,
  });

  factory Election.fromJson(Map<String, dynamic> json) {
    return Election(
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      title: json['title'] as String,
      description: json['description'] as String?,
      mediaType: MediaType.fromJson(json['media_type'] as Map<String, dynamic>),
      status: ElectionStatus.fromString(json['status'] as String),
      isMaestro: json['is_maestro'] as bool? ?? false,
      electionDate: DateTime.parse(json['election_date'] as String),
      deadline: DateTime.parse(json['deadline'] as String),
      campaignEnd: json['campaign_end'] != null
          ? DateTime.parse(json['campaign_end'] as String)
          : null,
      winnerCount: json['winner_count'] as int,
      candidateCount: json['candidate_count'] as int,
      voterCount: json['voter_count'] as int,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  @override
  List<Object?> get props => [id, uuid, title, status];
}

class ElectionDetail extends Election {
  final Maestro maestro;
  final List<Candidate> candidates;
  final List<Voter> voters;
  final bool allowSuggestions;
  final bool autoApprove;

  const ElectionDetail({
    required super.id,
    required super.uuid,
    required super.title,
    super.description,
    required super.mediaType,
    required super.status,
    required super.isMaestro,
    required super.electionDate,
    required super.deadline,
    super.campaignEnd,
    required super.winnerCount,
    required super.candidateCount,
    required super.voterCount,
    required super.createdAt,
    required this.maestro,
    required this.candidates,
    required this.voters,
    required this.allowSuggestions,
    required this.autoApprove,
  });

  factory ElectionDetail.fromJson(Map<String, dynamic> json) {
    return ElectionDetail(
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      title: json['title'] as String,
      description: json['description'] as String?,
      mediaType: MediaType.fromJson(json['media_type'] as Map<String, dynamic>),
      status: ElectionStatus.fromString(json['status'] as String),
      isMaestro: json['is_maestro'] as bool? ?? false,
      electionDate: DateTime.parse(json['election_date'] as String),
      deadline: DateTime.parse(json['deadline'] as String),
      campaignEnd: json['campaign_end'] != null
          ? DateTime.parse(json['campaign_end'] as String)
          : null,
      winnerCount: json['winner_count'] as int,
      candidateCount: (json['candidates'] as List?)?.length ?? json['candidate_count'] as int,
      voterCount: (json['voters'] as List?)?.length ?? json['voter_count'] as int,
      createdAt: DateTime.parse(json['created_at'] as String),
      maestro: Maestro.fromJson(json['maestro'] as Map<String, dynamic>),
      candidates: (json['candidates'] as List?)
          ?.map((c) => Candidate.fromJson(c as Map<String, dynamic>))
          .toList() ?? [],
      voters: (json['voters'] as List?)
          ?.map((v) => Voter.fromJson(v as Map<String, dynamic>))
          .toList() ?? [],
      allowSuggestions: json['allow_suggestions'] as bool? ?? false,
      autoApprove: json['auto_approve'] as bool? ?? false,
    );
  }
}

class Maestro extends Equatable {
  final int id;
  final String? displayName;
  final String? avatarUrl;

  const Maestro({
    required this.id,
    this.displayName,
    this.avatarUrl,
  });

  factory Maestro.fromJson(Map<String, dynamic> json) {
    return Maestro(
      id: json['id'] as int,
      displayName: json['display_name'] as String?,
      avatarUrl: json['avatar_url'] as String?,
    );
  }

  @override
  List<Object?> get props => [id, displayName];
}

class Voter extends Equatable {
  final int id;
  final VoterUser user;
  final DateTime joinedAt;
  final bool completed;

  const Voter({
    required this.id,
    required this.user,
    required this.joinedAt,
    required this.completed,
  });

  factory Voter.fromJson(Map<String, dynamic> json) {
    return Voter(
      id: json['id'] as int,
      user: VoterUser.fromJson(json['user'] as Map<String, dynamic>),
      joinedAt: DateTime.parse(json['joined_at'] as String),
      completed: json['completed'] as bool? ?? false,
    );
  }

  @override
  List<Object?> get props => [id, user];
}

class VoterUser extends Equatable {
  final int id;
  final String? displayName;
  final String? avatarUrl;

  const VoterUser({
    required this.id,
    this.displayName,
    this.avatarUrl,
  });

  factory VoterUser.fromJson(Map<String, dynamic> json) {
    return VoterUser(
      id: json['id'] as int,
      displayName: json['display_name'] as String?,
      avatarUrl: json['avatar_url'] as String?,
    );
  }

  @override
  List<Object?> get props => [id, displayName];
}
```

**Step 4: Commit**

```bash
git add .
git commit -m "feat: add election and candidate models"
```

---

## Task 2: Create Elections Provider

**Files:**
- Create: `frontend/lib/presentation/providers/elections_provider.dart`

**Step 1: Create elections provider**

Create `frontend/lib/presentation/providers/elections_provider.dart`:
```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/election_model.dart';
import '../../data/models/candidate_model.dart';
import 'auth_provider.dart';

// Elections list state
class ElectionsState {
  final List<Election> elections;
  final bool isLoading;
  final String? error;

  const ElectionsState({
    this.elections = const [],
    this.isLoading = false,
    this.error,
  });

  ElectionsState copyWith({
    List<Election>? elections,
    bool? isLoading,
    String? error,
  }) {
    return ElectionsState(
      elections: elections ?? this.elections,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}

// Elections list notifier
class ElectionsNotifier extends StateNotifier<ElectionsState> {
  final Ref _ref;

  ElectionsNotifier(this._ref) : super(const ElectionsState()) {
    loadElections();
  }

  Future<void> loadElections() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final apiClient = _ref.read(apiClientProvider);
      final response = await apiClient.get<Map<String, dynamic>>('/elections');
      final data = response['data'] as List;
      final elections = data
          .map((e) => Election.fromJson(e as Map<String, dynamic>))
          .toList();

      state = state.copyWith(elections: elections, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<ElectionDetail> createElection({
    required String title,
    String? description,
    required String mediaTypeCode,
    required int winnerCount,
    required DateTime electionDate,
    required DateTime deadline,
    DateTime? campaignEnd,
    bool allowSuggestions = false,
    bool autoApprove = false,
    required List<MediaItem> candidates,
  }) async {
    final apiClient = _ref.read(apiClientProvider);
    final response = await apiClient.post<Map<String, dynamic>>(
      '/elections',
      data: {
        'title': title,
        'description': description,
        'media_type_code': mediaTypeCode,
        'winner_count': winnerCount,
        'election_date': electionDate.toIso8601String(),
        'deadline': deadline.toIso8601String(),
        'campaign_end': campaignEnd?.toIso8601String(),
        'allow_suggestions': allowSuggestions,
        'auto_approve': autoApprove,
        'candidates': candidates.map((c) => {
          'external_id': c.externalId,
          'title': c.title,
          'poster_url': c.posterUrl,
          'year': c.year,
          'metadata': c.metadata,
        }).toList(),
      },
    );

    final election = ElectionDetail.fromJson(
      response['data'] as Map<String, dynamic>,
    );

    // Refresh list
    await loadElections();

    return election;
  }
}

final electionsProvider =
    StateNotifierProvider<ElectionsNotifier, ElectionsState>((ref) {
  return ElectionsNotifier(ref);
});

// Single election detail provider
final electionDetailProvider =
    FutureProvider.family<ElectionDetail, String>((ref, uuid) async {
  final apiClient = ref.read(apiClientProvider);
  final response = await apiClient.get<Map<String, dynamic>>('/elections/$uuid');
  return ElectionDetail.fromJson(response['data'] as Map<String, dynamic>);
});
```

**Step 2: Commit**

```bash
git add .
git commit -m "feat: add elections provider"
```

---

## Task 3: Create Media Search Provider

**Files:**
- Create: `frontend/lib/presentation/providers/media_search_provider.dart`

**Step 1: Create media search provider**

Create `frontend/lib/presentation/providers/media_search_provider.dart`:
```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/candidate_model.dart';
import 'auth_provider.dart';

class MediaSearchState {
  final List<MediaItem> results;
  final bool isLoading;
  final String? error;
  final int currentPage;
  final int totalPages;
  final bool hasMore;

  const MediaSearchState({
    this.results = const [],
    this.isLoading = false,
    this.error,
    this.currentPage = 1,
    this.totalPages = 1,
    this.hasMore = false,
  });

  MediaSearchState copyWith({
    List<MediaItem>? results,
    bool? isLoading,
    String? error,
    int? currentPage,
    int? totalPages,
    bool? hasMore,
  }) {
    return MediaSearchState(
      results: results ?? this.results,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      currentPage: currentPage ?? this.currentPage,
      totalPages: totalPages ?? this.totalPages,
      hasMore: hasMore ?? this.hasMore,
    );
  }
}

class MediaSearchNotifier extends StateNotifier<MediaSearchState> {
  final Ref _ref;
  String _lastQuery = '';
  String _lastType = '';

  MediaSearchNotifier(this._ref) : super(const MediaSearchState());

  Future<void> search(String type, String query) async {
    if (query.isEmpty) {
      state = const MediaSearchState();
      return;
    }

    _lastQuery = query;
    _lastType = type;

    state = state.copyWith(isLoading: true, error: null);

    try {
      final apiClient = _ref.read(apiClientProvider);
      final response = await apiClient.get<Map<String, dynamic>>(
        '/media/search',
        queryParameters: {'type': type, 'query': query, 'page': 1},
      );

      final data = response['data'] as List;
      final meta = response['meta'] as Map<String, dynamic>;

      final results = data
          .map((e) => MediaItem.fromJson(e as Map<String, dynamic>))
          .toList();

      state = state.copyWith(
        results: results,
        isLoading: false,
        currentPage: meta['current_page'] as int,
        totalPages: meta['total_pages'] as int,
        hasMore: (meta['current_page'] as int) < (meta['total_pages'] as int),
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> loadMore() async {
    if (state.isLoading || !state.hasMore) return;

    final nextPage = state.currentPage + 1;

    state = state.copyWith(isLoading: true);

    try {
      final apiClient = _ref.read(apiClientProvider);
      final response = await apiClient.get<Map<String, dynamic>>(
        '/media/search',
        queryParameters: {
          'type': _lastType,
          'query': _lastQuery,
          'page': nextPage,
        },
      );

      final data = response['data'] as List;
      final meta = response['meta'] as Map<String, dynamic>;

      final newResults = data
          .map((e) => MediaItem.fromJson(e as Map<String, dynamic>))
          .toList();

      state = state.copyWith(
        results: [...state.results, ...newResults],
        isLoading: false,
        currentPage: meta['current_page'] as int,
        totalPages: meta['total_pages'] as int,
        hasMore: (meta['current_page'] as int) < (meta['total_pages'] as int),
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  void clear() {
    state = const MediaSearchState();
    _lastQuery = '';
    _lastType = '';
  }
}

final mediaSearchProvider =
    StateNotifierProvider<MediaSearchNotifier, MediaSearchState>((ref) {
  return MediaSearchNotifier(ref);
});
```

**Step 2: Commit**

```bash
git add .
git commit -m "feat: add media search provider"
```

---

## Task 4: Create Voting Provider

**Files:**
- Create: `frontend/lib/data/models/duel_model.dart`
- Create: `frontend/lib/presentation/providers/voting_provider.dart`

**Step 1: Create Duel model**

Create `frontend/lib/data/models/duel_model.dart`:
```dart
import 'package:equatable/equatable.dart';
import 'candidate_model.dart';

class DuelProgress extends Equatable {
  final int completed;
  final int total;
  final double percentage;

  const DuelProgress({
    required this.completed,
    required this.total,
    required this.percentage,
  });

  factory DuelProgress.fromJson(Map<String, dynamic> json) {
    return DuelProgress(
      completed: json['completed'] as int,
      total: json['total'] as int,
      percentage: (json['percentage'] as num).toDouble(),
    );
  }

  @override
  List<Object?> get props => [completed, total, percentage];
}

class Duel extends Equatable {
  final Candidate candidateA;
  final Candidate candidateB;
  final DuelProgress progress;

  const Duel({
    required this.candidateA,
    required this.candidateB,
    required this.progress,
  });

  factory Duel.fromJson(Map<String, dynamic> json) {
    return Duel(
      candidateA: Candidate.fromJson(json['candidate_a'] as Map<String, dynamic>),
      candidateB: Candidate.fromJson(json['candidate_b'] as Map<String, dynamic>),
      progress: DuelProgress.fromJson(json['progress'] as Map<String, dynamic>),
    );
  }

  @override
  List<Object?> get props => [candidateA, candidateB];
}
```

**Step 2: Create voting provider**

Create `frontend/lib/presentation/providers/voting_provider.dart`:
```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/duel_model.dart';
import 'auth_provider.dart';

class VotingState {
  final Duel? currentDuel;
  final bool isComplete;
  final bool isLoading;
  final bool isVoting;
  final String? error;

  const VotingState({
    this.currentDuel,
    this.isComplete = false,
    this.isLoading = false,
    this.isVoting = false,
    this.error,
  });

  VotingState copyWith({
    Duel? currentDuel,
    bool? isComplete,
    bool? isLoading,
    bool? isVoting,
    String? error,
  }) {
    return VotingState(
      currentDuel: currentDuel ?? this.currentDuel,
      isComplete: isComplete ?? this.isComplete,
      isLoading: isLoading ?? this.isLoading,
      isVoting: isVoting ?? this.isVoting,
      error: error,
    );
  }
}

class VotingNotifier extends StateNotifier<VotingState> {
  final Ref _ref;
  final String _electionUuid;

  VotingNotifier(this._ref, this._electionUuid) : super(const VotingState()) {
    loadNextDuel();
  }

  Future<void> loadNextDuel() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final apiClient = _ref.read(apiClientProvider);
      final response = await apiClient.get<Map<String, dynamic>>(
        '/elections/$_electionUuid/vote/next',  // Updated endpoint
      );

      final data = response['data'];

      if (data == null) {
        state = state.copyWith(isComplete: true, isLoading: false);
        return;
      }

      final duel = Duel.fromJson(data as Map<String, dynamic>);
      state = state.copyWith(currentDuel: duel, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> vote(int winnerId) async {
    await _submitVote(winnerId);
  }

  Future<void> skip() async {
    await _submitVote(null);
  }

  Future<void> _submitVote(int? winnerId) async {
    final duel = state.currentDuel;
    if (duel == null) return;

    state = state.copyWith(isVoting: true, error: null);

    try {
      final apiClient = _ref.read(apiClientProvider);
      final response = await apiClient.post<Map<String, dynamic>>(
        '/elections/$_electionUuid/vote',
        data: {
          'candidate_a_id': duel.candidateA.id,
          'candidate_b_id': duel.candidateB.id,
          'winner_id': winnerId,  // null for skip
        },
      );

      final data = response['data'] as Map<String, dynamic>;
      final nextDuelData = data['next_duel'];

      if (nextDuelData == null) {
        state = state.copyWith(isComplete: true, isVoting: false);
        return;
      }

      final progress = DuelProgress.fromJson(data['progress'] as Map<String, dynamic>);
      final nextDuel = Duel(
        candidateA: Candidate.fromJson(nextDuelData['candidate_a'] as Map<String, dynamic>),
        candidateB: Candidate.fromJson(nextDuelData['candidate_b'] as Map<String, dynamic>),
        progress: progress,
      );

      state = state.copyWith(currentDuel: nextDuel, isVoting: false);
    } catch (e) {
      state = state.copyWith(isVoting: false, error: e.toString());
    }
  }
}

final votingProvider =
    StateNotifierProvider.family<VotingNotifier, VotingState, String>((ref, uuid) {
  return VotingNotifier(ref, uuid);
});
```

**Step 3: Commit**

```bash
git add .
git commit -m "feat: add voting provider"
```

---

## Task 5: Create Election Card Widget

**Files:**
- Create: `frontend/lib/presentation/widgets/election/election_card.dart`
- Create: `frontend/lib/presentation/widgets/election/election_status_badge.dart`

**Step 1: Create election status badge**

Create `frontend/lib/presentation/widgets/election/election_status_badge.dart`:
```dart
import 'package:flutter/material.dart';
import '../../../data/models/election_model.dart';

class ElectionStatusBadge extends StatelessWidget {
  final ElectionStatus status;

  const ElectionStatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      decoration: BoxDecoration(
        color: _getBackgroundColor(),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        _getLabel(),
        style: TextStyle(
          color: _getTextColor(),
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  String _getLabel() {
    switch (status) {
      case ElectionStatus.campaign:
        return 'Campaign';
      case ElectionStatus.voting:
        return 'Voting';
      case ElectionStatus.ended:
        return 'Ended';
      case ElectionStatus.archived:
        return 'Archived';
      case ElectionStatus.draft:
        return 'Draft';
    }
  }

  Color _getBackgroundColor() {
    switch (status) {
      case ElectionStatus.campaign:
        return Colors.orange.shade100;
      case ElectionStatus.voting:
        return Colors.green.shade100;
      case ElectionStatus.ended:
        return Colors.blue.shade100;
      case ElectionStatus.archived:
        return Colors.grey.shade200;
      case ElectionStatus.draft:
        return Colors.grey.shade100;
    }
  }

  Color _getTextColor() {
    switch (status) {
      case ElectionStatus.campaign:
        return Colors.orange.shade800;
      case ElectionStatus.voting:
        return Colors.green.shade800;
      case ElectionStatus.ended:
        return Colors.blue.shade800;
      case ElectionStatus.archived:
        return Colors.grey.shade700;
      case ElectionStatus.draft:
        return Colors.grey.shade600;
    }
  }
}
```

**Step 2: Create election card**

Create `frontend/lib/presentation/widgets/election/election_card.dart`:
```dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../data/models/election_model.dart';
import 'election_status_badge.dart';

class ElectionCard extends StatelessWidget {
  final Election election;
  final VoidCallback? onTap;

  const ElectionCard({
    super.key,
    required this.election,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('MMM d, y');

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      election.title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  ElectionStatusBadge(status: election.status),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(
                    _getMediaTypeIcon(election.mediaType.code),
                    size: 16,
                    color: Colors.grey,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    election.mediaType.label,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.grey,
                    ),
                  ),
                  const SizedBox(width: 16),
                  const Icon(Icons.people, size: 16, color: Colors.grey),
                  const SizedBox(width: 4),
                  Text(
                    '${election.voterCount} voters',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.grey,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.event, size: 16, color: Colors.grey),
                  const SizedBox(width: 4),
                  Text(
                    dateFormat.format(election.electionDate),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.grey,
                    ),
                  ),
                  if (election.isMaestro) ...[
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primary.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        'Maestro',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.primary,
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  IconData _getMediaTypeIcon(String code) {
    switch (code) {
      case 'movie':
        return Icons.movie;
      case 'tvshow':
        return Icons.tv;
      case 'videogame':
        return Icons.sports_esports;
      // Placeholders for future media types (see docs/future-ideas.md)
      case 'boardgame':
        return Icons.extension;
      case 'theater':
        return Icons.theater_comedy;
      default:
        return Icons.category;
    }
  }
}
```

**Step 3: Commit**

```bash
git add .
git commit -m "feat: add election card widget"
```

---

## Task 6: Update Home Screen with Elections List

**Files:**
- Modify: `frontend/lib/presentation/screens/home/home_screen.dart`

**Step 1: Update home screen**

Replace `frontend/lib/presentation/screens/home/home_screen.dart`:
```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/auth_provider.dart';
import '../../providers/elections_provider.dart';
import '../../widgets/election/election_card.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final electionsState = ref.watch(electionsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('ScreenBuddies'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.read(electionsProvider.notifier).loadElections(),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
      ),
      body: _buildBody(context, ref, authState, electionsState),
      floatingActionButton: authState.user?.emailVerified == true
          ? FloatingActionButton.extended(
              onPressed: () => context.push('/election/create'),
              icon: const Icon(Icons.add),
              label: const Text('New Election'),
            )
          : null,
    );
  }

  Widget _buildBody(
    BuildContext context,
    WidgetRef ref,
    AuthState authState,
    ElectionsState electionsState,
  ) {
    if (authState.user?.emailVerified == false) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.email, size: 64, color: Colors.orange),
              const SizedBox(height: 16),
              Text(
                'Verify Your Email',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              const Text(
                'Please verify your email address to create elections.',
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      );
    }

    if (electionsState.isLoading && electionsState.elections.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (electionsState.error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('Error: ${electionsState.error}'),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => ref.read(electionsProvider.notifier).loadElections(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (electionsState.elections.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.how_to_vote, size: 64, color: Colors.grey),
            const SizedBox(height: 16),
            Text(
              'No Elections Yet',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 8),
            const Text('Create your first election to get started!'),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(electionsProvider.notifier).loadElections(),
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: electionsState.elections.length,
        itemBuilder: (context, index) {
          final election = electionsState.elections[index];
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: ElectionCard(
              election: election,
              onTap: () => context.push('/election/${election.uuid}'),
            ),
          );
        },
      ),
    );
  }
}
```

**Step 2: Commit**

```bash
git add .
git commit -m "feat: update home screen with elections list"
```

---

## Task 7: Create Duel Screen

**Files:**
- Create: `frontend/lib/presentation/screens/voting/duel_screen.dart`
- Create: `frontend/lib/presentation/widgets/duel/duel_card.dart`

**Step 1: Create duel card widget**

Create `frontend/lib/presentation/widgets/duel/duel_card.dart`:
```dart
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../data/models/candidate_model.dart';

class DuelCard extends StatelessWidget {
  final Candidate candidate;
  final VoidCallback onTap;
  final bool isSelected;
  final bool isLoading;

  const DuelCard({
    super.key,
    required this.candidate,
    required this.onTap,
    this.isSelected = false,
    this.isLoading = false,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: isLoading ? null : onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected
                ? Theme.of(context).colorScheme.primary
                : Colors.transparent,
            width: 3,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.1),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: Stack(
            children: [
              // Poster image
              if (candidate.posterUrl != null)
                CachedNetworkImage(
                  imageUrl: candidate.posterUrl!,
                  fit: BoxFit.cover,
                  width: double.infinity,
                  height: double.infinity,
                  placeholder: (context, url) => Container(
                    color: Colors.grey.shade200,
                    child: const Center(child: CircularProgressIndicator()),
                  ),
                  errorWidget: (context, url, error) => Container(
                    color: Colors.grey.shade200,
                    child: const Icon(Icons.movie, size: 48),
                  ),
                )
              else
                Container(
                  color: Colors.grey.shade200,
                  child: const Center(
                    child: Icon(Icons.movie, size: 48, color: Colors.grey),
                  ),
                ),
              // Gradient overlay
              Positioned.fill(
                child: Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.transparent,
                        Colors.black.withOpacity(0.8),
                      ],
                    ),
                  ),
                ),
              ),
              // Title and year
              Positioned(
                left: 16,
                right: 16,
                bottom: 16,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      candidate.title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (candidate.year != null)
                      Text(
                        candidate.year.toString(),
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.8),
                          fontSize: 14,
                        ),
                      ),
                  ],
                ),
              ),
              // Loading overlay
              if (isLoading)
                Positioned.fill(
                  child: Container(
                    color: Colors.black.withOpacity(0.5),
                    child: const Center(
                      child: CircularProgressIndicator(color: Colors.white),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
```

**Step 2: Create duel screen**

Create `frontend/lib/presentation/screens/voting/duel_screen.dart`:
```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/voting_provider.dart';
import '../../widgets/duel/duel_card.dart';

class DuelScreen extends ConsumerStatefulWidget {
  final String electionUuid;

  const DuelScreen({super.key, required this.electionUuid});

  @override
  ConsumerState<DuelScreen> createState() => _DuelScreenState();
}

class _DuelScreenState extends ConsumerState<DuelScreen> {
  int? _selectedId;

  @override
  Widget build(BuildContext context) {
    final votingState = ref.watch(votingProvider(widget.electionUuid));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Vote'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => context.pop(),
        ),
      ),
      body: _buildBody(context, votingState),
    );
  }

  Widget _buildBody(BuildContext context, VotingState state) {
    if (state.isLoading && state.currentDuel == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.isComplete) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.check_circle, size: 80, color: Colors.green),
              const SizedBox(height: 24),
              Text(
                'All Done!',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 8),
              const Text(
                'You have completed all duels.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () => context.pop(),
                child: const Text('Back to Election'),
              ),
            ],
          ),
        ),
      );
    }

    if (state.error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('Error: ${state.error}'),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => ref
                  .read(votingProvider(widget.electionUuid).notifier)
                  .loadNextDuel(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    final duel = state.currentDuel!;

    return SafeArea(
      child: Column(
        children: [
          // Progress
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                LinearProgressIndicator(
                  value: duel.progress.percentage / 100,
                  backgroundColor: Colors.grey.shade200,
                ),
                const SizedBox(height: 8),
                Text(
                  '${duel.progress.completed} / ${duel.progress.total} duels',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          // Instruction
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              'Tap your favorite',
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          const SizedBox(height: 16),
          // Duel cards
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                children: [
                  Expanded(
                    child: DuelCard(
                      candidate: duel.candidateA,
                      isSelected: _selectedId == duel.candidateA.id,
                      isLoading: state.isVoting && _selectedId == duel.candidateA.id,
                      onTap: () => _vote(duel.candidateA.id),
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Text(
                      'VS',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: Colors.grey,
                      ),
                    ),
                  ),
                  Expanded(
                    child: DuelCard(
                      candidate: duel.candidateB,
                      isSelected: _selectedId == duel.candidateB.id,
                      isLoading: state.isVoting && _selectedId == duel.candidateB.id,
                      onTap: () => _vote(duel.candidateB.id),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          // Skip button
          TextButton.icon(
            onPressed: state.isVoting ? null : _skip,
            icon: const Icon(Icons.skip_next),
            label: const Text("Don't know either? Skip"),
            style: TextButton.styleFrom(
              foregroundColor: Colors.grey,
            ),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  void _vote(int winnerId) {
    setState(() {
      _selectedId = winnerId;
    });

    ref.read(votingProvider(widget.electionUuid).notifier).vote(winnerId).then((_) {
      setState(() {
        _selectedId = null;
      });
    });
  }

  void _skip() {
    ref.read(votingProvider(widget.electionUuid).notifier).skip();
  }
}
```

**Step 3: Commit**

```bash
git add .
git commit -m "feat: add duel screen for voting"
```

---

## Task 8: Update Router with New Routes

**Files:**
- Modify: `frontend/lib/presentation/router/app_router.dart`

**Step 1: Update router**

Update `frontend/lib/presentation/router/app_router.dart` to add new routes:
```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../screens/splash_screen.dart';
import '../screens/auth/login_screen.dart';
import '../screens/auth/register_screen.dart';
import '../screens/home/home_screen.dart';
import '../screens/voting/duel_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);

  return GoRouter(
    initialLocation: '/splash',
    debugLogDiagnostics: true,
    redirect: (context, state) {
      final isAuth = authState.status == AuthStatus.authenticated;
      final isLoading = authState.status == AuthStatus.unknown;
      final isSplash = state.matchedLocation == '/splash';
      final isAuthRoute = state.matchedLocation.startsWith('/auth');

      if (isLoading) {
        return isSplash ? null : '/splash';
      }

      if (!isAuth && !isAuthRoute && !isSplash) {
        return '/auth/login';
      }

      if (isAuth && (isAuthRoute || isSplash)) {
        return '/';
      }

      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: '/auth/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/auth/register',
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(
        path: '/',
        builder: (context, state) => const HomeScreen(),
      ),
      GoRoute(
        path: '/election/create',
        builder: (context, state) => const Placeholder(), // TODO: CreateElectionScreen
      ),
      GoRoute(
        path: '/election/:uuid',
        builder: (context, state) {
          final uuid = state.pathParameters['uuid']!;
          return Placeholder(); // TODO: ElectionDetailScreen
        },
      ),
      GoRoute(
        path: '/election/:uuid/vote',
        builder: (context, state) {
          final uuid = state.pathParameters['uuid']!;
          return DuelScreen(electionUuid: uuid);
        },
      ),
    ],
  );
});
```

**Step 2: Commit**

```bash
git add .
git commit -m "feat: update router with election routes"
```

---

## Task 9: Final Integration Test

**Step 1: Run the app**

```bash
cd frontend && flutter run -d chrome
```

**Step 2: Test the flow**

1. Register/Login
2. View empty elections list
3. (Backend must be running for full test)

**Step 3: Final commit**

```bash
git add .
git commit -m "chore: phase 6 complete - flutter election features"
```

---

## Phase 6 Completion Checklist

- [ ] Election models (Election, ElectionDetail, Candidate, Voter, Duel)
- [ ] Media search provider
- [ ] Elections provider (list, create)
- [ ] Voting provider (load next duel, cast vote via `/vote/next` and `/vote` endpoints)
- [ ] Election card widget
- [ ] Election status badge widget
- [ ] Home screen with elections list
- [ ] Duel card widget
- [ ] Duel/voting screen
- [ ] Router updated with election routes
- [ ] Integration test passed

---

## Remaining Work (Future Phases)

These features are scaffolded but need full implementation:

1. **Election Creation Flow**
   - Multi-step wizard (type selection, details, candidates, review)
   - Media search integration in candidate selection
   - Date/time pickers for deadlines

2. **Election Detail Screen**
   - Full election information display
   - Candidates list
   - Voters list
   - Actions (start voting, close, share invite link)

3. **Results Screen**
   - Winners display
   - Full ranking
   - Statistics

4. **Join Election Flow**
   - Handle `/join/{invite_token}` deep links
   - Share invite link functionality
   - Join election confirmation screen

5. **Notifications**
   - Firebase Cloud Messaging setup
   - Push notification handling
   - Deep linking from notifications
