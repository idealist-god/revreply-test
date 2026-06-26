# RevReply Architecture & Design

## Overview

RevReply is an intelligent email automation system that integrates with Gmail to automatically classify incoming emails and generate contextual draft replies. It's designed for sales teams to efficiently manage high-volume inbound email while maintaining a human touch in responses.

**Core Function**: Watch Gmail inboxes → Fetch new emails → Classify by intent → Generate appropriate draft replies → Present to user for review/approval

---

## System Architecture

### High-Level Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER INTERFACE                          │
│              (Frontend - React/Vue Application)                 │
└──────────────────────┬──────────────────────────────────────────┘
                       │ HTTP API (REST)
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                     LARAVEL API LAYER                           │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Controllers: UserAuth, GmailAccount, Thread, Draft         │ │
│  │ Routes: /auth/*, /accounts/*, /threads/*, /drafts/*       │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────────────┬──────────────────────────────────────────┘
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
    ┌────────┐   ┌──────────┐   ┌─────────────┐
    │Database│   │  Redis   │   │Google OAuth │
    │(MySQL) │   │ (Cache & │   │  / Gmail    │
    │        │   │  Queue)  │   │   API       │
    └────────┘   └──────────┘   └─────────────┘
        │              │              │
        └──────────────┼──────────────┘
                       │ (Models, Jobs, Services)
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                   ASYNC JOB PROCESSING LAYER                    │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ FetchAndClassifyEmailJob → ClassifyEmailJob              │ │
│  │ GenerateDraftJob → DraftReply Creation                   │ │
│  │ ProcessGmailHistoryJob → Gmail Sync Loop                │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────────────┬──────────────────────────────────────────┘
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│   Gmail      │ │   OpenAI     │ │  Monitoring  │
│   Watch      │ │   API        │ │   & Logging  │
│              │ │ (gpt-4o-mini)│ │              │
└──────────────┘ └──────────────┘ └──────────────┘

┌─────────────────────────────────────────────────────────────────┐
│              GOOGLE CLOUD PUB/SUB (Event Listener)              │
│  Receives notifications when new emails arrive → Webhooks       │
└─────────────────────────────────────────────────────────────────┘
```

### Component Architecture

```
CORE SERVICES (Business Logic):
├── GoogleOAuthService
│   └── Manages OAuth flow, token refresh, client creation
├── GmailMessageService
│   └── Fetch messages, parse email bodies, sync to DB
├── GmailWatchService
│   └── Setup Gmail inbox watching via Cloud Pub/Sub
├── ClassificationService
│   └── Use OpenAI to classify emails into intent categories
├── DraftGenerationService
│   └── Use OpenAI to generate contextually appropriate replies
└── (Future) GoogleSyncService (in progress)

ASYNC JOBS (Queue Processing):
├── ProcessGmailHistoryJob
│   └── Periodic: Fetch new message IDs from Gmail history
├── FetchAndClassifyEmailJob
│   └── Sync a message + dispatch ClassifyEmailJob
├── ClassifyEmailJob
│   └── Call ClassificationService, update thread classification
├── GenerateDraftJob
│   └── Generate reply via OpenAI, create Gmail draft
└── (Future) Job retry logic improvements

MODELS (Data Layer):
├── User
├── GmailAccount (OAuth tokens, watch state)
├── EmailThread (consolidated email conversation)
├── EmailMessage (individual message in thread)
├── DraftReply (AI-generated reply draft)
└── ClassificationLog (audit trail of classifications)
```

---

## Data Model

### Database Schema

```
users
├── id (PK)
├── name
├── email (unique)
├── password (hashed)
├── email_verified_at
├── created_at / updated_at

gmail_accounts
├── id (PK)
├── user_id (FK → users)
├── gmail_address (unique)
├── access_token (encrypted)
├── refresh_token (encrypted)
├── token_expires_at
├── watch_history_id
├── watch_expires_at
└── created_at / updated_at

email_threads
├── id (PK)
├── gmail_account_id (FK → gmail_accounts)
├── gmail_thread_id (unique with account_id)
├── subject
├── snippet
├── from_address
├── last_message_at
├── classification (interested | not_interested | meeting_request | unclear)
├── status (active | draft_created | dismissed | closed)
├── created_at / updated_at

email_messages
├── id (PK)
├── email_thread_id (FK → email_threads)
├── gmail_message_id (unique)
├── from_address
├── to_address
├── subject
├── body_text (plain text)
├── body_html
├── received_at
├── processed_at
├── created_at / updated_at

draft_replies
├── id (PK)
├── email_thread_id (FK → email_threads)
├── email_message_id (FK → email_messages)
├── body (generated reply text)
├── gmail_draft_id
├── status (pending | approved | sent | rejected)
├── sent_at
├── created_at / updated_at

classification_logs
├── id (PK)
├── email_message_id (FK → email_messages)
├── classification
├── confidence (0.0-1.0)
├── llm_provider (openai | stub)
├── model (gpt-4o-mini, etc)
├── prompt_tokens
├── completion_tokens
├── cost_usd
├── raw_response (JSON)
├── created_at

jobs (Laravel queue table)
├── id
├── queue
├── payload
├── attempts
├── reserved_at
├── available_at
├── created_at
```

### Entity Relationships

```
User              1 ──── ∞ GmailAccount
GmailAccount      1 ──── ∞ EmailThread
EmailThread       1 ──── ∞ EmailMessage
EmailMessage      1 ──── ∞ DraftReply
EmailMessage      1 ──── ∞ ClassificationLog
EmailThread       1 ──── ∞ DraftReply
```

---

## Feature Workflows

### 1. User Authentication & Gmail Connection

```
User Registration
  ↓
User Login → Get OAuth redirect URL
  ↓
Google OAuth Consent Screen
  ↓
Receive auth_code + exchange for tokens
  ↓
Store encrypted access/refresh tokens in gmail_accounts
  ↓
Start watching inbox (Gmail.watch API)
```

### 2. Inbound Email Processing

```
Gmail Inbox (New Email Arrives)
  ↓ (Gmail Push Notification via Cloud Pub/Sub)
/gmail/webhook endpoint receives notification
  ↓
ProcessGmailHistoryJob retrieves new message IDs
  ↓
For each message ID:
  ├─→ FetchAndClassifyEmailJob
  │    ├─→ GmailMessageService.syncMessage()
  │    │    └─→ Parse email, create/update EmailThread & EmailMessage
  │    └─→ Dispatch ClassifyEmailJob
  │
  └─→ ClassifyEmailJob
       ├─→ ClassificationService.classify()
       │    └─→ OpenAI GPT-4o-mini call with email body
       │    └─→ Parse JSON response (classification + confidence)
       │    └─→ Log classification metadata (tokens, cost, etc)
       └─→ Dispatch GenerateDraftJob
            ├─→ DraftGenerationService.generate()
            │    └─→ OpenAI GPT-4o-mini call with email context
            │    └─→ Generate natural reply text (max 150 words)
            └─→ GmailMessageService.createDraft()
                 └─→ Create Gmail Draft via Gmail API
                 └─→ Store DraftReply in database (status=pending)
```

### 3. User Review & Action

```
User opens app → Sees email thread with draft reply
  ├─→ Approve Draft
  │    └─→ DraftController.approve()
  │         └─→ Send draft to inbox
  │         └─→ Update DraftReply.status = sent, set sent_at
  │
  ├─→ Edit & Send Draft
  │    └─→ DraftController.update()
  │         └─→ Update DraftReply.body with user edits
  │         └─→ Send to inbox
  │
  ├─→ Regenerate Draft
  │    └─→ DraftController.regenerate()
  │         └─→ Call OpenAI again with different temperature/parameters
  │         └─→ Create new DraftReply
  │
  └─→ Reject Draft
       └─→ DraftController.reject()
            └─→ Update DraftReply.status = rejected
            └─→ Optionally regenerate
```

---

## Scaling Design & Failure Handling

### Horizontal Scaling Strategy

#### 1. **Queue Processing**

- **Current**: Database queue with Redis cache backend
- **Bottleneck**: Single queue worker processing jobs sequentially
- **Scale To**: Multiple queue worker processes (via `php artisan queue:work --processes=N`)
- **Design**: Jobs are immutable, stateless, and idempotent → safe to process in parallel
- **Consideration**: Classification & generation jobs use `->onQueue('classify')` to separate workloads

#### 2. **Database Performance**

- **Indexes** (should exist):
    - `gmail_accounts(user_id, gmail_address)`
    - `email_threads(gmail_account_id, gmail_thread_id)`
    - `email_messages(email_thread_id, gmail_message_id)`
    - `email_messages(processed_at)` for background sync queries
    - `classification_logs(email_message_id)`

- **Read Scaling**:
    - Cache thread/message lists in Redis with 5-min TTL
    - Lazy-load paginated threads (50 per page)
    - Use database read replicas for analytics queries

#### 3. **API Rate Limiting**

- **Gmail API**:
    - 15M requests/day quota practically unlimited for small teams
    - Watch API: 1 renewal every 6 days per account (no practical limit)
    - History API: ~2-3 requests per sync cycle
- **OpenAI API**:
    - Implementation caps cost via `ClassificationLog` tracking
    - Could add daily spend limits per user/account
    - Fallback to stubs when API fails (safety net)

#### 4. **Concurrency & Race Conditions**

Built-in protections:

```php
// FetchAndClassifyEmailJob prevents duplicate processing
if (EmailMessage::where('gmail_message_id', $id)->exists()) {
    return; // Skip if already synced
}

// GenerateDraftJob prevents duplicate drafts
if (DraftReply::where('email_message_id', $id)
    ->whereIn('status', ['pending', 'approved', 'sent'])
    ->exists()) {
    return; // Skip if draft exists
}
```

### Failure Handling & Resilience

#### 1. **Job Retry Strategy**

```php
public int $tries = 3;
public array $backoff = [30, 60, 120]; // 30s, 60s, 2min delays
```

- **Rationale**: Temporary API outages or rate limits recover within seconds
- **Silent Failures**: If all retries exhausted, logged and abandoned (not escalated)
- **Missing Data**: If Gmail returns 404 for message, logged and skipped (likely deleted)

#### 2. **Token Management**

```php
protected function casts(): array {
    return [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];
}
```

- **Automatic Refresh**: GoogleOAuthService refreshes tokens before expiry
- **Revoked Tokens**: If user revokes access, Gmail API returns 401 → job fails → logged
- **Recovery**: User can re-authenticate via UI

#### 3. **API Resilience**

```php
try {
    $response = $this->openai->chat()->create([...]);
} catch (\Throwable $e) {
    Log::warning('OpenAI failed, using fallback stub', ['error' => $e->getMessage()]);
    return $this->generateStub($classification);
}
```

- **Graceful Degradation**: Falls back to templated responses
- **Cost Protection**: Stub mode logs zero cost, useful for testing/staging

#### 4. **Data Consistency**

- **Atomic Transactions**: Gmail message sync is atomic (message + thread created together)
- **Idempotent Operations**: All jobs check if work already done before proceeding
- **Orphaned Drafts**: If draft creation fails, message stays in DB for retry
- **Deleted Messages**: If Gmail message deleted between fetch and process, gracefully skipped with 404 handling

#### 5. **Monitoring & Alerts**

Implemented via Laravel Logs + Application Monitoring:

```php
Log::error('FetchAndClassifyEmailJob failed', [
    'gmail_account_id' => $account->id,
    'gmail_message_id' => $id,
    'error' => $e->getMessage(),
]);
```

**What's Monitored**:

- Job failures (all logged with context)
- OpenAI API calls (token usage, cost tracked in `ClassificationLog`)
- Token refresh issues
- Gmail API errors (401, 403, 429, 500+)

### High-Load Considerations

#### Bottleneck #1: OpenAI API Calls

**Current Load**: ~2 calls per email (classify + generate) @ $0.0001-0.0002 per call

**At 1,000 emails/day**: ~10 seconds of API time, ~$0.30/day (minimal)

**At 100,000 emails/day** (enterprise scale):

- Cost: ~$30/day
- Rate limits: OpenAI tier depends on account (starts 3K RPM)
- **Mitigation**:
    - Batch API calls (OpenAI supports batch mode)
    - Classification caching (cache reply templates for common classifications)
    - Model downgrade (gpt-4-turbo → gpt-3.5-turbo for non-critical drafts)

#### Bottleneck #2: Database I/O

**At 100,000 emails/day**: ~125 inserts/second peak

**Mitigation**:

- Batch inserts via Laravel's `insert()` for history syncs
- Read replicas for analytics/UI queries
- Partition `email_messages` by date range (annual partitioning)

#### Bottleneck #3: Gmail API Rate Limiting

**Unlikely**: 15M requests/day quota, peak ~100 requests/day for even large deployment

**Mitigation**:

- Implement exponential backoff (already in job retry config)
- Reuse watch history instead of full IMAP sync

---

## Deliberately Skipped & Trade-offs

### 1. ✋ **Full Email Threading with MIME Structure**

**What We Skipped**: Complex MIME parsing (attachments, inline images, multipart alternatives)

**Why**:

- Initial use case: Text-only email classification
- Complications: Attachment scanning, image extraction, HTML sanitization
- Cost: Would require additional libraries (symfony/mime, mailparse)
- Alternative: Store raw message ID, fetch full structure only when needed

**Future**: Add attachment detection + basic metadata when user zooms into thread

---

### 2. ✋ **User Customization of LLM Prompts**

**What We Skipped**: Dynamic system prompts, per-user classification categories

**Why**:

- Scope creep: Requires prompt versioning, A/B testing infrastructure
- Risk: Users can break classification with bad prompts
- Complexity: Need to version prompts alongside results for reproducibility

**Current**: Hardcoded 4-category classification system for all users

**Future**: Could add admin-level prompt templates, A/B test framework

---

### 3. ✋ **Draft Approval Workflow with Comments/Edits History**

**What We Skipped**: Collaboration features (comment threads, edit history, approval chains)

**Why**:

- Scope: Designed for individual power users initially
- Complexity: Adds audit trail, versioning, conflict resolution
- Alternative: Simple edit field + send (good enough for single user approval)

**Current**: User can edit draft body directly, no history tracking

**Future**: Add edit history, comments for team collaboration features

---

### 4. ✋ **Advanced Analytics & Reporting**

**What We Skipped**: Classification accuracy metrics, cost analysis UI, performance dashboards

**Why**:

- Data exists but no presentation layer
- Complexity: Requires frontend components + aggregation queries
- Alternative: Raw data in `ClassificationLog` can be exported to analytics tools

**Current**: Classification metrics stored but hidden from UI

**Future**: Add dashboard showing:

- Classification accuracy over time
- API cost per user/account
- Response time distribution
- Draft approval rates

---

### 5. ✋ **Multi-Tenant Account Isolation**

**What We Skipped**: Hard tenant boundaries, row-level security policies

**Why**:

- Current design: Single user per app session
- Assumption: Deployed per-user or small team (not SaaS)
- Risk: Requires paranoid checking at every endpoint
- Alternative: Current user context from Sanctum token is sufficient for MVP

**Current**: `User::find(auth()->id())` in controllers relies on Sanctum token

**Future**: If expanding to SaaS, add:

- Row-level security (Laravel policies)
- Audit logging of all data access
- Tenant-scoped job queues

---

### 6. ✋ **Email Sending Delays & Scheduling**

**What We Skipped**: Schedule drafts for future send, send time optimization (best time to reply)

**Why**:

- Complexity: Needs cron jobs, timezone handling, user timezone detection
- Use case: Not needed for urgent reply classification (sales)
- Alternative: User can manually send from Gmail UI or via API immediately

**Current**: All approvals → immediate send

**Future**: Queue drafts, add delay options, send time optimization

---

### 7. ✋ **Multi-Provider LLM Support**

**What We Skipped**: Fallback to Claude/Gemini, multi-model voting, cost comparison

**Why**:

- Scope: OpenAI has best API reliability for this task
- Maintenance: Each provider needs custom parsing + error handling
- Cost: Minimal savings (gpt-4o-mini already very cheap)

**Current**: OpenAI only, with stub fallback

**Future**: Could add Anthropic Claude as fallback for drafts (similar quality, different rate limits)

---

### 8. ✋ **Advanced Conversation Context**

**What We Skipped**: Full thread history in classification/generation prompts

**Why**:

- Complexity: Requires intelligent conversation reconstruction (avoid noise)
- Token limits: Older messages use up token budget
- Diminishing returns: Last message often sufficient for classification

**Current**: Only classify latest message in thread

**Future**: Pass last 3-5 message summaries to improve context awareness

---

### 9. ✋ **Browser Offline Mode & Local Caching**

**What We Skipped**: Service workers, offline queue, local draft storage

**Why**:

- Complexity: Need conflict resolution, sync strategy
- Assumption: Sales team has reliable internet connection
- Alternative: Drafts saved server-side, hard to lose

**Current**: All data server-stored, client is stateless

**Future**: Add read-only offline mode + optimistic updates

---

### 10. ✋ **Batch Email Import**

**What We Skipped**: CSV/bulk import of historical emails, migration from other systems

**Why**:

- Use case: System watches live email, not historical analysis
- Complexity: Large CSV parsing, deduplication, thread reconstruction
- Alternative: Let Gmail Watch API catch emails over time

**Current**: Only processes emails after user connects account

**Future**: Migration tool for teams switching from other solutions

---

## Performance Characteristics

### Single Email Processing Pipeline

```
FetchAndClassifyEmailJob
├─ GmailMessageService.syncMessage()        ~300ms (Gmail API call)
├─ ClassifyEmailJob
│  └─ ClassificationService.classify()      ~800ms (OpenAI API call)
├─ GenerateDraftJob
│  ├─ OpenAI generation                     ~1200ms
│  └─ Gmail draft creation                  ~200ms
└─ Total end-to-end                         ~2-3 seconds (async)
```

### User Request Latencies

- **GET /threads**: ~50ms (database paginated query)
- **GET /threads/{id}**: ~30ms (single thread load)
- **POST /drafts/{id}/approve**: ~1.5s (Gmail API call)
- **GET /auth/google/redirect**: ~100ms (OAuth redirect)

---

## What to Build Next

### Phase 2: Team Collaboration

1. **Shared Drafts Review**
    - Assign drafts to team members for approval
    - Comment/feedback system
    - Edit audit trail

2. **Category Customization**
    - Allow teams to define custom classification categories
    - Per-team LLM prompts

3. **Analytics Dashboard**
    - Classification accuracy metrics
    - API cost tracking
    - Response time performance
    - Team productivity (emails handled, approval rates)

### Phase 3: Advanced Automation

1. **Smart Routing**
    - Route emails to specific team members based on classification
    - Auto-assign drafts based on expertise tags

2. **Approval Workflows**
    - Multi-level approval chains
    - Escalation rules for low-confidence classifications

3. **Integration Ecosystem**
    - Slack notifications
    - HubSpot/Salesforce sync
    - Custom webhook integrations

### Phase 4: Intelligence & Learning

1. **Classification Feedback Loop**
    - Track user corrections to classification
    - Retrain/prompt-tune on domain examples
    - Confidence scoring improvements

2. **Personalized Templates**
    - Learn user writing style from approved drafts
    - Generate drafts matching individual voice/tone

3. **Predictive Actions**
    - Predict next steps after each email
    - Suggest follow-up timing

### Phase 5: Scale & Enterprise

1. **Multi-Account Management**
    - Manage multiple Gmail accounts per user
    - Cross-account thread correlation

2. **Advanced Email Features**
    - Reply with attachments
    - Email signature templates
    - Scheduling/send-time optimization

3. **Compliance & Security**
    - SOC 2 compliance
    - Data retention policies
    - Encryption at rest

---

## Technical Debt & Known Limitations

### Short Term

- [ ] Add database indexes for performance (query optimization)
- [ ] Add test coverage (unit tests for services, feature tests for jobs)
- [ ] Improve error messages in API responses (more specific than 500 errors)
- [ ] Add request validation (ensure email format, body length, etc)
- [ ] Rate limiting on public endpoints (webhook, Google callback)

### Medium Term

- [ ] Decouple Gmail sync from REST API (move to background processor)
- [ ] Add structured logging (JSON format for log aggregation)
- [ ] Implement circuit breaker for OpenAI API failures
- [ ] Cache classification prompts/results (avoid reprocessing same email)
- [ ] Add database migration versioning for schema tracking

### Long Term

- [ ] Replace database queue with Redis (better for high-volume)
- [ ] Move to event-driven architecture (event sourcing)
- [ ] Add GraphQL API layer (more flexible for UI)
- [ ] Implement multi-tenancy properly (row-level security)
- [ ] Consider microservices split (API / Workers / Processing)

---

## Development & Deployment

### Local Development

```bash
# Setup
composer install
npm install
php artisan migrate:fresh
php artisan serve

# Queue worker
php artisan queue:listen

# Watch Vite
npm run dev
```

### Environment Variables (Required)

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
GOOGLE_PUBSUB_TOPIC=
OPENAI_API_KEY=
LLM_MODEL=gpt-4o-mini
LLM_STUB=false
QUEUE_CONNECTION=redis
```

### Deployment Strategy

- **Web Server**: Laravel on PHP 8.2+ with FPM
- **Queue Workers**: Process jobs via Redis
- **Database**: MySQL 8.0+
- **Cache**: Redis (session store + queue broker)
- **Monitoring**: Application Insights / Sentry integration

### Testing Strategy

Need to add:

- Unit tests for Services (ClassificationService, DraftGenerationService)
- Feature tests for API endpoints
- Job tests (FetchAndClassifyEmailJob, GenerateDraftJob)
- Mocks for Gmail API and OpenAI API

---

## References

### External APIs

- [Gmail API Documentation](https://developers.google.com/gmail/api)
- [Google Cloud Pub/Sub](https://cloud.google.com/pubsub/docs)
- [OpenAI Chat Completions](https://platform.openai.com/docs/api-reference/chat/create)

### Laravel Documentation

- [Queue System](https://laravel.com/docs/queues)
- [Eloquent ORM](https://laravel.com/docs/eloquent)
- [Service Providers & Dependency Injection](https://laravel.com/docs/providers)
- [API Authentication with Sanctum](https://laravel.com/docs/sanctum)

### Core Dependencies

- `google/apiclient` - Google APIs
- `openai-php/client` - OpenAI SDK
- `predis/predis` - Redis client
- `laravel/sanctum` - API authentication
- `laravel/framework` - Web framework (v12)

---

**Last Updated**: June 2026  
**Architecture Version**: 1.0  
**Status**: MVP with scaling roadmap
