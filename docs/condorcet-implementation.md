# “Active Top-k Condorcet Ranking via Confidence-Weighted Ranked Pairs”

Below is a **clean, implementation-ready algorithm specification** for **Laravel / PHP 8**.
No theory, no fluff — just what you need to build it correctly.

---

# 🎯 Goal

* Input: **pairwise duels only** (A vs B)
* Constraints: **sparse data**, **few duels per voter**
* Output: **top-k candidates**
* Method:

  * **Active duel selection**
  * **Robust Condorcet aggregation**
  * **Ranked Pairs (Tideman)**

---

# 1️⃣ Data Model (compact JSON storage)

### Tables

#### `candidates`

```text
id (int, PK)
election_id (FK)
```

#### `voters`

Each voter stores all their duel votes in a compact JSON blob.

```text
id (PK)
election_id (FK)
user_id (FK)
votes (JSON)      -- {"1_2": 1, "1_3": null, "2_3": 2, ...}
duel_count (int)  -- counts only actual votes (not skips)
completed (bool)
```

**Votes JSON format:** Key = `{smaller_id}_{larger_id}`, Value = winner's candidate ID or `null` if skipped.

**Skipped duels:** When a voter doesn't know either candidate, they can skip. Skipped duels are stored as `null` and are **ignored** in ranking calculations.

#### `pairwise_stats` (computed on demand)

Aggregated from all Voter.votes JSON blobs in an election.

```text
candidate_i
candidate_j
wins_ij
wins_ji
total
```

Compute on the fly or cache (5 min TTL recommended).

---

# 2️⃣ Core Parameters (tunable constants)

```php
ALPHA = 1;          // Beta prior (Laplace smoothing)
Z_CONFIDENCE = 1.96; // z-score for 95% confidence (or 1.28 for 80%)
K = 5;              // top-k
```

> **IMPORTANT:** Do NOT use a fixed `MIN_PAIR_DUELS` threshold. Use the Confidence Interval Reliability Rule (see Section 3.5).

---

# 3️⃣ Robust Pairwise Strength (RSV core)

For each unordered pair (i, j):

### Step 1 — Counts

```php
w_ij = wins(i > j)
w_ji = wins(j > i)
n    = w_ij + w_ji
```

### Step 2 — Smoothed probability

```php
p_ij = (w_ij + ALPHA) / (n + 2*ALPHA)
```

### Step 3 — Robust strength

```php
strength_ij = max(0, (p_ij - 0.5) * sqrt(n))
```

### Step 4 — Reliability Check (Confidence Interval Rule)

A pair is **reliable** if and only if the confidence interval for the true win probability **excludes 0.5**.

**Statistical Foundation:**
- Each duel outcome is a Bernoulli trial with unknown true probability P(i,j)
- We observe w_ij wins, w_ji losses, n = w_ij + w_ji total
- We want to trust the direction of preference only when statistically confident

**Formula (Normal Approximation):**

```php
// Observed win rate
$p_hat = $w_ij / $n;

// Standard error
$se = sqrt($p_hat * (1 - $p_hat) / $n);

// Confidence bounds (z = 1.96 for 95%, z = 1.28 for 80%)
$lcb = $p_hat - Z_CONFIDENCE * $se;  // Lower Confidence Bound
$ucb = $p_hat + Z_CONFIDENCE * $se;  // Upper Confidence Bound

// Reliability condition: CI must not cross 0.5
$is_reliable = ($lcb > 0.5) || ($ucb < 0.5);
```

**Reliability Condition:**
```
Pair is reliable ⟺ LCB > 0.5 OR UCB < 0.5
```

**Why This Works:**

| Observed | n | p̂ | LCB (95%) | Reliable? | Reason |
|----------|---|-----|-----------|-----------|--------|
| 8-2 | 10 | 0.80 | 0.55 | ✅ | Strong margin, CI above 0.5 |
| 6-4 | 10 | 0.60 | 0.30 | ❌ | Moderate margin, CI crosses 0.5 |
| 15-10 | 25 | 0.60 | 0.41 | ❌ | Still crosses 0.5 |
| 18-7 | 25 | 0.72 | 0.54 | ✅ | More data confirms direction |
| 3-0 | 3 | 1.00 | 0.29 | ❌ | Perfect but insufficient data |
| 10-0 | 10 | 1.00 | 0.69 | ✅ | Unanimous with enough data |

**Key Properties:**
- **Strong margins** → fewer observations needed
- **Close races** → more observations needed
- **Self-calibrating** → adapts to actual vote patterns
- **No arbitrary thresholds** → statistically principled

➡️ This ensures we only trust pairs where we have **statistical confidence** in the direction of preference.

---

# 4️⃣ Ranked Pairs (Condorcet Aggregation)

### Step 4.1 — Build edge list

For every pair (i, j):

```php
if (strength_ij > 0):
    edge = (i → j, weight = strength_ij)
```

### Step 4.2 — Sort edges

```php
edges = sort_descending_by_weight(edges)
```

### Step 4.3 — Lock edges (cycle-safe)

```php
graph = empty directed graph

for edge in edges:
    if !createsCycle(graph, edge):
        graph.add(edge)
```

> Cycle check = DFS or Union-Find with direction.

---

# 5️⃣ Extract Top-k

### Step 5.1 — Rank candidates

Rank by:

1. Number of outgoing edges
2. Sum of outgoing edge weights
3. Total duel count
4. Candidate ID (deterministic fallback)

### Step 5.2 — Output

```php
return top K candidates
```

---

# 6️⃣ Active Duel Selection (to minimize questions)

This is **critical**.

### Step 6.1 — Maintain Copeland bounds

For each candidate `i`:

```php
C_minus[i] = count(j where LCB_ij > 0.5)
C_plus[i]  = count(j where UCB_ij > 0.5)
```

(LCB/UCB from Beta posterior)

---

### Step 6.2 — Elimination rule

Candidate `i` is eliminated if:

```php
C_plus[i] < kth_highest(C_minus)
```

➡️ Eliminated candidates stop receiving duels.

---

### Step 6.3 — Duel priority score

Only consider **frontier candidates**:

```php
frontier = top_k + next 3 candidates
```

For each eligible pair (i, j):

```php
priority(i,j) =
    (0.5 - abs(p_ij - 0.5)) / sqrt(n_ij + 1)
```

Pick the **max priority** pair.

➡️ This focuses on:

* Uncertain
* Under-sampled
* Top-k-relevant duels

---

# 7️⃣ Stopping Condition (important)

You may **stop asking duels** when:

```php
for all outside candidates o:
    C_plus[o] < C_minus[kth]
```

Meaning:

> No remaining candidate can mathematically enter the top-k.

---

# 8️⃣ Tie-Breaking Rules (deterministic)

Always apply in this order:

1. Higher Copeland score
2. Higher total robust margin
3. More total duels
4. Candidate ID

Never random.

---

# 9️⃣ Complexity (safe for Laravel)

* Pairwise stats: **O(n²)** (cached)
* Ranked Pairs: **O(n² log n)**
* Cycle check: **O(n)** per edge
* Works comfortably up to **n ≈ 100**

---

# 🔚 Final Recommendation (production default)

| Component         | Choice                                      |
| ----------------- | ------------------------------------------- |
| Condorcet variant | **Ranked Pairs**                            |
| Robustness        | Beta smoothing + √n penalty                 |
| Duel selection    | Copeland bounds + uncertainty               |
| Output            | Top-k only                                  |
| Reliability       | **Confidence interval rule** (95%, z=1.96)  |

---

If you want next:

* ✅ **Laravel service class skeleton**
* ✅ **PostgreSQL schema + indexes**
* ✅ **Exact PHP code for cycle detection**
* ✅ **API flow (controller → service → result)**

Just tell me.

