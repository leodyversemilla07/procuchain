//! HTTP boundary for the ProcuChain application.
//!
//! The domain and persistence crates remain usable without an HTTP server. This
//! crate owns the versioned API router so transport concerns do not leak into
//! the domain model.

use std::{
    collections::{BTreeMap, BTreeSet},
    sync::{Arc, Mutex},
    time::{Duration, Instant},
};

use axum::{
    Json, Router,
    body::Bytes,
    extract::{Path, Request, State},
    http::{HeaderMap, HeaderValue, StatusCode},
    middleware::{self, Next},
    response::{IntoResponse, Response},
    routing::{delete, get, post},
};
use procuchain_app::Platform;
use procuchain_issuance::{
    AcknowledgementMethod, DeliveryResult, IssuanceArtifact, IssuanceRecipient,
};
use procuchain_postgres::{IdempotencyClaim, PgStore, StoreError};
use procuchain_procurement::{AppContent, PpmpContent};
use procuchain_records::{DocumentType, RulePackRef, SchemaRef, TemplateRef};
use procuchain_shared::{
    Actor, ActorRole, ContentHash, DocumentVersionId, DomainError, RulePackSnapshotId, TenantId,
    UserId, WorkflowTaskId,
};
use procuchain_signatures::SignaturePolicy;
use procuchain_signatures::{SignatureMethod, SignaturePurpose, SignatureValidation};
use procuchain_storage::{FilesystemObjectStore, ObjectStore, StorageError};
use procuchain_workflow::{BacMembershipSnapshot, BacVote, WorkflowTask};
use serde::{Deserialize, Serialize};
use time::{OffsetDateTime, format_description::well_known::Rfc3339};
use tower_http::cors::CorsLayer;
use tower_http::services::ServeDir;
use uuid::Uuid;

const SESSION_COOKIE: &str = "procuchain_session";

/// Mutable application state owned by the API process.
///
/// The actor is configured by the server and is intentionally not accepted in
/// the request payload. Authentication will replace this development actor at
/// the API boundary before the Development is production-ready.
#[derive(Clone)]
pub struct ApiState {
    store: PgStore,
    object_store: FilesystemObjectStore,
    tenant_id: TenantId,
    actor: Actor,
    hope_actor: Actor,
    _bearer_token: Arc<str>,
    rate_limiter: Arc<RateLimiter>,
}

struct RateLimiter {
    requests: Mutex<BTreeMap<String, (Instant, u32)>>,
    limit: u32,
    window: Duration,
}

impl RateLimiter {
    fn new(limit: u32, window: Duration) -> Self {
        Self {
            requests: Mutex::new(BTreeMap::new()),
            limit,
            window,
        }
    }

    fn allow(&self, key: &str) -> bool {
        let mut requests = self
            .requests
            .lock()
            .expect("rate limiter mutex is not poisoned");
        let now = Instant::now();
        let entry = requests.entry(key.to_owned()).or_insert((now, 0));
        if now.duration_since(entry.0) >= self.window {
            *entry = (now, 0);
        }
        if entry.1 >= self.limit {
            return false;
        }
        entry.1 += 1;
        true
    }
}

impl ApiState {
    pub fn new(store: PgStore, tenant_id: TenantId, actor: Actor) -> Self {
        Self::with_bearer_token(store, tenant_id, actor, "procuchain-development-token")
    }

    pub fn with_bearer_token(
        store: PgStore,
        tenant_id: TenantId,
        actor: Actor,
        bearer_token: impl Into<Arc<str>>,
    ) -> Self {
        Self {
            store,
            object_store: FilesystemObjectStore::new(
                std::env::var("PROCUCHAIN_OBJECT_ROOT")
                    .unwrap_or_else(|_| "storage/objects".to_owned()),
            )
            .expect("the configured object storage root must be writable"),
            tenant_id,
            actor: actor.clone(),
            hope_actor: Actor::new(
                actor.user_id(),
                ActorRole::Hope,
                "Development Head of the Procuring Entity",
            ),
            _bearer_token: bearer_token.into(),
            rate_limiter: Arc::new(RateLimiter::new(120, Duration::from_secs(60))),
        }
    }
}

/// Build the health-only router used by infrastructure probes and tests.
pub fn router() -> Router {
    Router::new()
        .route("/api/v1/health", get(health))
        .route("/workbench", get(workbench_shell))
        .nest_service(
            "/build",
            ServeDir::new(concat!(env!("CARGO_MANIFEST_DIR"), "/../../public/build")),
        )
        .layer(middleware::from_fn(security_headers))
        .layer(middleware::from_fn(correlation_id))
}

async fn workbench_shell() -> Result<axum::response::Html<String>, StatusCode> {
    let manifest = std::fs::read_to_string(concat!(
        env!("CARGO_MANIFEST_DIR"),
        "/../../public/build/manifest.json"
    ))
    .map_err(|_| StatusCode::NOT_FOUND)?;
    let manifest: serde_json::Value =
        serde_json::from_str(&manifest).map_err(|_| StatusCode::INTERNAL_SERVER_ERROR)?;
    let entry = manifest
        .get("resources/js/app.tsx")
        .and_then(|entry| entry.get("file"))
        .and_then(serde_json::Value::as_str)
        .ok_or(StatusCode::INTERNAL_SERVER_ERROR)?;
    let styles = manifest
        .get("resources/js/app.tsx")
        .and_then(|entry| entry.get("css"))
        .and_then(serde_json::Value::as_array)
        .into_iter()
        .flatten()
        .filter_map(serde_json::Value::as_str)
        .map(|style| format!("<link rel=\"stylesheet\" href=\"/build/{style}\">"))
        .collect::<String>();
    let page = serde_json::json!({"component": "workbench", "props": {}, "url": "/workbench", "version": null});
    let page = serde_json::to_string(&page).map_err(|_| StatusCode::INTERNAL_SERVER_ERROR)?;
    Ok(axum::response::Html(format!(
        "<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">{styles}</head><body><script type=\"application/json\" data-page=\"app\">{page}</script><div id=\"app\"></div><script type=\"module\" src=\"/build/{entry}\"></script></body></html>"
    )))
}

/// Build the versioned API router with application state.
pub fn router_with_state(state: ApiState) -> Router {
    Router::<ApiState>::new()
        .route("/api/v1/health", get(health))
        .route("/workbench", get(workbench_shell))
        .nest_service(
            "/build",
            ServeDir::new(concat!(env!("CARGO_MANIFEST_DIR"), "/../../public/build")),
        )
        .route("/api/v1/auth/session", post(create_session))
        .route("/api/v1/auth/session", delete(delete_session))
        .route("/api/v1/workflow/tasks", post(create_workflow_task))
        .route("/api/v1/workflow/tasks/{task_id}", get(get_workflow_task))
        .route(
            "/api/v1/workflow/tasks/{task_id}/claim",
            post(claim_workflow_task),
        )
        .route(
            "/api/v1/workflow/tasks/{task_id}/complete",
            post(complete_workflow_task),
        )
        .route("/api/v1/me", get(current_principal))
        .route(
            "/api/v1/committees/{committee_id}/members",
            get(committee_members),
        )
        .route("/api/v1/ppmp", post(create_ppmp))
        .route("/api/v1/ppmp/{version_id}", get(get_ppmp))
        .route(
            "/api/v1/ppmp/{version_id}/indicative-app",
            post(create_indicative_app),
        )
        .route(
            "/api/v1/app/{version_id}/bac-resolution",
            post(create_bac_resolution),
        )
        .route(
            "/api/v1/app/{version_id}/hope-approval",
            post(create_hope_approval),
        )
        .route(
            "/api/v1/app/{version_id}/signature-request",
            post(create_signature_request),
        )
        .route(
            "/api/v1/signature-requests/{request_id}/complete",
            post(complete_signature),
        )
        .route("/api/v1/app/{version_id}/issue", post(issue_app))
        .route(
            "/api/v1/issuances/{issuance_id}/artifacts/{artifact_id}",
            post(upload_issuance_artifact),
        )
        .route(
            "/api/v1/documents/{version_id}/submit",
            post(submit_document),
        )
        .route(
            "/api/v1/distributions/{distribution_id}/deliver",
            post(record_delivery),
        )
        .route(
            "/api/v1/distributions/{distribution_id}/acknowledge",
            post(acknowledge_distribution),
        )
        .layer(CorsLayer::permissive())
        .layer(middleware::from_fn_with_state(state.clone(), rate_limit))
        .layer(middleware::from_fn(security_headers))
        .layer(middleware::from_fn(correlation_id))
        .with_state(state)
}

async fn security_headers(request: Request, next: Next) -> Response {
    let mut response = next.run(request).await;
    let headers = response.headers_mut();
    headers.insert(
        "x-content-type-options",
        HeaderValue::from_static("nosniff"),
    );
    headers.insert("x-frame-options", HeaderValue::from_static("DENY"));
    headers.insert("referrer-policy", HeaderValue::from_static("no-referrer"));
    headers.insert(
        "content-security-policy",
        HeaderValue::from_static(
            "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'",
        ),
    );
    response
}

async fn rate_limit(State(state): State<ApiState>, request: Request, next: Next) -> Response {
    let key = request
        .headers()
        .get("x-forwarded-for")
        .and_then(|value| value.to_str().ok())
        .and_then(|value| value.split(',').next())
        .map(str::trim)
        .filter(|value| !value.is_empty())
        .unwrap_or("unknown");
    if state.rate_limiter.allow(key) {
        return next.run(request).await;
    }
    (StatusCode::TOO_MANY_REQUESTS, "rate limit exceeded").into_response()
}

#[derive(Debug, Serialize)]
struct HealthResponse {
    status: &'static str,
    service: &'static str,
    api_version: &'static str,
}

#[derive(Debug, Serialize)]
struct SessionResponse {
    expires_at: String,
}

async fn create_session(
    State(state): State<ApiState>,
    headers: HeaderMap,
) -> Result<(StatusCode, HeaderMap, Json<SessionResponse>), ApiError> {
    let token = bearer_token(&headers).ok_or_else(ApiError::unauthorized)?;
    let identity = state
        .store
        .resolve_api_token(state.tenant_id, token)
        .await?
        .ok_or_else(ApiError::unauthorized)?;
    if !identity.active {
        return Err(ApiError::unauthorized());
    }
    let expires_at = OffsetDateTime::now_utc() + time::Duration::hours(8);
    let session = state
        .store
        .create_session(state.tenant_id, identity.user_id, expires_at)
        .await?;
    let secure = if std::env::var("PROCUCHAIN_COOKIE_SECURE").as_deref() == Ok("true") {
        "; Secure"
    } else {
        ""
    };
    let mut response_headers = HeaderMap::new();
    response_headers.insert(
        "set-cookie",
        HeaderValue::from_str(&format!(
            "{SESSION_COOKIE}={session}; HttpOnly; SameSite=Lax; Path=/api/v1; Max-Age=28800{secure}"
        ))
        .map_err(|_| ApiError::internal("could not create session cookie"))?,
    );
    Ok((
        StatusCode::CREATED,
        response_headers,
        Json(SessionResponse {
            expires_at: expires_at
                .format(&Rfc3339)
                .map_err(|_| ApiError::internal("could not format session expiry"))?,
        }),
    ))
}

async fn delete_session(
    State(state): State<ApiState>,
    headers: HeaderMap,
) -> Result<StatusCode, ApiError> {
    if let Some(session) = session_cookie(&headers) {
        state
            .store
            .revoke_session(state.tenant_id, session, OffsetDateTime::now_utc())
            .await?;
    }
    Ok(StatusCode::NO_CONTENT)
}

async fn health() -> Json<HealthResponse> {
    Json(HealthResponse {
        status: "ok",
        service: "procuchain",
        api_version: "v1",
    })
}

async fn correlation_id(request: Request, next: Next) -> Response {
    let correlation_id = request
        .headers()
        .get("x-correlation-id")
        .and_then(|value| value.to_str().ok())
        .filter(|value| !value.is_empty() && value.len() <= 128)
        .map(str::to_owned)
        .unwrap_or_else(|| Uuid::now_v7().to_string());
    let mut response = next.run(request).await;
    if !response.headers().contains_key("x-correlation-id")
        && let Ok(value) = HeaderValue::from_str(&correlation_id)
    {
        response.headers_mut().insert("x-correlation-id", value);
    }
    response
}

#[derive(Debug, Serialize)]
struct PrincipalResponse {
    user_id: String,
    display_name: String,
    roles: Vec<String>,
}

#[derive(Debug, Serialize)]
struct CommitteeMemberResponse {
    user_id: String,
    display_name: String,
    seat: String,
}

async fn current_principal(
    State(state): State<ApiState>,
    headers: HeaderMap,
) -> Result<Json<PrincipalResponse>, ApiError> {
    let identity = authenticated_identity(&state, &headers).await?;
    Ok(Json(PrincipalResponse {
        user_id: identity.user_id.to_string(),
        display_name: identity.display_name,
        roles: identity
            .roles
            .iter()
            .map(|role| role.label().to_owned())
            .collect(),
    }))
}

async fn committee_members(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(committee_id): Path<Uuid>,
) -> Result<Json<Vec<CommitteeMemberResponse>>, ApiError> {
    authenticate(&state, &headers).await?;
    let members = state
        .store
        .committee_members(
            state.tenant_id,
            procuchain_shared::CommitteeId::from_uuid(committee_id),
        )
        .await?;
    Ok(Json(
        members
            .into_iter()
            .map(|member| CommitteeMemberResponse {
                user_id: member.user_id.to_string(),
                display_name: member.display_name,
                seat: member.seat.label().to_owned(),
            })
            .collect(),
    ))
}

#[derive(Debug, Deserialize, Serialize)]
pub struct CreatePpmpRequest {
    pub title: String,
    pub content: PpmpContent,
    pub template_id: String,
    pub template_version: String,
    pub template_authority: String,
    pub schema_id: String,
    pub schema_version: String,
    pub rule_pack_snapshot_id: Uuid,
    pub rule_pack_id: String,
    pub rule_pack_hash: String,
}

const PPMP_TEMPLATE_ID: &str = "ppmp-template";
const PPMP_TEMPLATE_VERSION: &str = "2026.1";
const PPMP_SCHEMA_ID: &str = "ppmp-schema";
const PPMP_SCHEMA_VERSION: &str = "1.0";

#[derive(Debug, Deserialize, Serialize)]
pub struct CreateIndicativeAppRequest {
    pub title: String,
    pub template_id: String,
    pub template_version: String,
    pub template_authority: String,
    pub schema_id: String,
    pub schema_version: String,
    pub rule_pack_snapshot_id: Uuid,
    pub rule_pack_id: String,
    pub rule_pack_hash: String,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct CreateBacResolutionRequest {
    pub resolution_number: String,
    pub membership: BacMembershipSnapshot,
    pub attendance: Vec<Uuid>,
    pub votes: Vec<BacVoteInput>,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct BacVoteInput {
    pub user_id: Uuid,
    pub vote: BacVote,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct CreateHopeApprovalRequest {
    pub authority_ref: String,
    pub grounds: Option<String>,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct CreateSignatureRequest {
    pub purpose: SignaturePurpose,
    pub method: SignatureMethod,
    pub declaration: String,
    pub deadline: String,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct CompleteSignatureRequest {
    pub signed_at: String,
    pub provider_transaction: String,
    pub credential_ref: String,
    pub signed_artifact_hash: String,
    pub validation: SignatureValidation,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct IssueAppRequest {
    pub authority_ref: String,
    pub artifacts: Vec<IssuanceArtifact>,
    pub recipients: Vec<IssuanceRecipient>,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct RecordDeliveryRequest {
    pub channel_ref: String,
    pub result: DeliveryResult,
    pub attempted_at: String,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct AcknowledgeDistributionRequest {
    pub recipient_id: Uuid,
    pub artifact_hash: String,
    pub method: AcknowledgementMethod,
    pub declaration: Option<String>,
    pub acknowledged_at: String,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct CreateWorkflowTaskRequest {
    pub task_type: String,
    pub subject_ref: String,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct CompleteWorkflowTaskRequest {
    pub outcome: String,
}

#[derive(Debug, Serialize)]
struct WorkflowTaskResponse {
    id: String,
    task_type: String,
    subject_ref: String,
    assignee_id: Option<String>,
    status: String,
    outcome: Option<String>,
    row_version: i64,
}

#[derive(Debug, Deserialize, Serialize)]
struct CreatePpmpResponse {
    document_id: String,
    version_id: String,
    content_hash: String,
}

#[derive(Debug, Serialize)]
struct PpmpResponse {
    document_id: String,
    version_id: String,
    version_number: u32,
    status: String,
    content: serde_json::Value,
    content_hash: String,
}

#[derive(Debug, Deserialize, Serialize)]
struct BacResolutionResponse {
    resolution_id: String,
    app_version_id: String,
    outcome: String,
}

#[derive(Debug, Deserialize, Serialize)]
struct HopeApprovalResponse {
    approval_id: String,
    app_version_id: String,
    status: String,
}

#[derive(Debug, Deserialize, Serialize)]
struct SignatureResponse {
    signature_request_id: String,
    signature_id: Option<String>,
    app_version_id: String,
    status: String,
}

#[derive(Debug, Deserialize, Serialize)]
struct IssuanceResponse {
    issuance_id: String,
    app_version_id: String,
    distribution_ids: Vec<String>,
    manifest_hash: String,
}

#[derive(Debug, Deserialize, Serialize)]
struct DistributionResponse {
    distribution_id: String,
    acknowledgement_id: Option<String>,
    status: String,
}

#[derive(Debug, Serialize)]
struct ArtifactUploadResponse {
    object_key: String,
    content_hash: String,
    byte_length: u64,
}

#[derive(Debug, Deserialize, Serialize)]
struct SubmittedResponse {
    version_id: String,
    status: String,
}

fn workflow_task_response(task: &WorkflowTask) -> WorkflowTaskResponse {
    WorkflowTaskResponse {
        id: task.id().to_string(),
        task_type: task.task_type().to_owned(),
        subject_ref: task.subject_ref().to_owned(),
        assignee_id: task.assignee().map(|id| id.to_string()),
        status: task.status().label().to_owned(),
        outcome: task.outcome().map(str::to_owned),
        row_version: task.row_version(),
    }
}

async fn create_workflow_task(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Json(request): Json<CreateWorkflowTaskRequest>,
) -> Result<(StatusCode, Json<WorkflowTaskResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    let task = WorkflowTask::create(
        WorkflowTaskId::new(),
        state.tenant_id,
        request.task_type,
        request.subject_ref,
    )
    .map_err(ApiError::domain)?;
    let response = workflow_task_response(&task);
    let mut unit = state.store.begin().await?;
    unit.insert_workflow_task(&task).await?;
    unit.commit().await?;
    Ok((StatusCode::CREATED, Json(response)))
}

async fn get_workflow_task(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(task_id): Path<Uuid>,
) -> Result<Json<WorkflowTaskResponse>, ApiError> {
    authenticate(&state, &headers).await?;
    let task = state
        .store
        .load_workflow_task(state.tenant_id, WorkflowTaskId::from_uuid(task_id))
        .await?
        .ok_or_else(|| ApiError::not_found("workflow task"))?;
    Ok(Json(workflow_task_response(&task)))
}

async fn claim_workflow_task(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(task_id): Path<Uuid>,
) -> Result<Json<WorkflowTaskResponse>, ApiError> {
    let identity = authenticated_identity(&state, &headers).await?;
    let id = WorkflowTaskId::from_uuid(task_id);
    let task = state
        .store
        .load_workflow_task(state.tenant_id, id)
        .await?
        .ok_or_else(|| ApiError::not_found("workflow task"))?;
    let expected = task.row_version();
    let mut updated = task;
    updated.claim(identity.user_id).map_err(ApiError::domain)?;
    let response = workflow_task_response(&updated);
    let mut unit = state.store.begin().await?;
    if !unit
        .update_workflow_task_if_row_version(&updated, expected)
        .await?
    {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "workflow task changed; refresh it and retry",
        ));
    }
    unit.commit().await?;
    Ok(Json(response))
}

async fn complete_workflow_task(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(task_id): Path<Uuid>,
    Json(request): Json<CompleteWorkflowTaskRequest>,
) -> Result<Json<WorkflowTaskResponse>, ApiError> {
    let identity = authenticated_identity(&state, &headers).await?;
    let id = WorkflowTaskId::from_uuid(task_id);
    let task = state
        .store
        .load_workflow_task(state.tenant_id, id)
        .await?
        .ok_or_else(|| ApiError::not_found("workflow task"))?;
    let expected = task.row_version();
    let mut updated = task;
    updated
        .complete(identity.user_id, request.outcome)
        .map_err(ApiError::domain)?;
    let response = workflow_task_response(&updated);
    let mut unit = state.store.begin().await?;
    if !unit
        .update_workflow_task_if_row_version(&updated, expected)
        .await?
    {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "workflow task changed; refresh it and retry",
        ));
    }
    unit.commit().await?;
    Ok(Json(response))
}

async fn create_ppmp(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Json(request): Json<CreatePpmpRequest>,
) -> Result<(StatusCode, Json<CreatePpmpResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    validate_ppmp_request(&request)?;
    let idempotency_key = headers
        .get("idempotency-key")
        .map(|value| {
            value
                .to_str()
                .map(str::trim)
                .map(str::to_owned)
                .map_err(|_| ApiError::bad_request("Idempotency-Key must be valid ASCII"))
        })
        .transpose()?
        .filter(|value| !value.is_empty());
    if idempotency_key.as_ref().is_some_and(|key| key.len() > 200) {
        return Err(ApiError::bad_request(
            "Idempotency-Key must be at most 200 characters",
        ));
    }
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    )
    .to_string();
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(state.tenant_id, key, "ppmp.create", &request_hash)
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: CreatePpmpResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                let status = StatusCode::from_u16(status as u16)
                    .map_err(|_| ApiError::internal("stored idempotent status is invalid"))?;
                return Ok((status, Json(response)));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let template = TemplateRef::new(
        request.template_id,
        request.template_version,
        request.template_authority,
    )
    .map_err(ApiError::domain)?;
    let schema =
        SchemaRef::new(request.schema_id, request.schema_version).map_err(ApiError::domain)?;
    let rule_pack_hash = ContentHash::from_hex(request.rule_pack_hash).map_err(ApiError::domain)?;
    let rule_pack = RulePackRef::new(
        RulePackSnapshotId::from_uuid(request.rule_pack_snapshot_id),
        request.rule_pack_id,
        rule_pack_hash,
    )
    .map_err(ApiError::domain)?;

    let mut platform = Platform::new(state.tenant_id, SignaturePolicy::new());
    let version_id = platform
        .create_ppmp_draft(
            request.title,
            request.content,
            template,
            schema,
            state.actor,
            rule_pack,
        )
        .map_err(ApiError::domain)?;
    let version = platform
        .version(version_id)
        .ok_or_else(|| ApiError::not_found("created PPMP version"))?
        .clone();
    let document = platform
        .document(version.document_id())
        .ok_or_else(|| ApiError::not_found("created PPMP document"))?
        .clone();
    let event = platform
        .audit_events()
        .last()
        .ok_or_else(|| ApiError::not_found("created PPMP audit event"))?
        .clone();

    unit.save_document(&document).await?;
    unit.save_document_version(state.tenant_id, &version)
        .await?;
    unit.append_audit_event(
        state.tenant_id,
        event.event_type,
        event.actor,
        event.subject_kind,
        event.subject_id,
        event.occurred_at,
        event.details,
    )
    .await?;
    let response = CreatePpmpResponse {
        document_id: version.document_id().to_string(),
        version_id: version.id().to_string(),
        content_hash: version.content_hash().to_string(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(
            state.tenant_id,
            key,
            StatusCode::CREATED.as_u16() as i32,
            &body,
        )
        .await?;
    }
    unit.commit().await?;

    Ok((StatusCode::CREATED, Json(response)))
}

fn validate_ppmp_request(request: &CreatePpmpRequest) -> Result<(), ApiError> {
    let checks = [
        (
            "template_id",
            request.template_id == PPMP_TEMPLATE_ID,
            format!("must reference the registered {PPMP_TEMPLATE_ID} template"),
        ),
        (
            "template_version",
            request.template_version == PPMP_TEMPLATE_VERSION,
            format!("must use registered template version {PPMP_TEMPLATE_VERSION}"),
        ),
        (
            "schema_id",
            request.schema_id == PPMP_SCHEMA_ID,
            format!("must reference the registered {PPMP_SCHEMA_ID} schema"),
        ),
        (
            "schema_version",
            request.schema_version == PPMP_SCHEMA_VERSION,
            format!("must use registered schema version {PPMP_SCHEMA_VERSION}"),
        ),
    ];
    if let Some((field, _, message)) = checks.into_iter().find(|(_, valid, _)| !valid) {
        return Err(ApiError::validation(format!("{field} {message}")));
    }
    if request.template_authority.trim().is_empty() {
        return Err(ApiError::validation(
            "template_authority must identify the approving template register",
        ));
    }
    Ok(())
}

async fn get_ppmp(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(version_id): Path<Uuid>,
) -> Result<Json<PpmpResponse>, ApiError> {
    authenticate(&state, &headers).await?;
    let version_id = DocumentVersionId::from_uuid(version_id);
    let version = state
        .store
        .load_document_version(state.tenant_id, version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("PPMP version"))?;
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("PPMP document"))?;
    if document.document_type() != DocumentType::Ppmp {
        return Err(ApiError::not_found("PPMP version"));
    }

    Ok(Json(PpmpResponse {
        document_id: version.document_id().to_string(),
        version_id: version.id().to_string(),
        version_number: version.version_number(),
        status: version.status().label().to_owned(),
        content: serde_json::Value::Object(
            version.content().fields().clone().into_iter().collect(),
        ),
        content_hash: version.content_hash().to_string(),
    }))
}

async fn submit_document(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(version_id): Path<Uuid>,
) -> Result<Json<SubmittedResponse>, ApiError> {
    authenticate(&state, &headers).await?;
    let version_id = DocumentVersionId::from_uuid(version_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let fingerprint = format!("documents.submit:{}:{:?}", version_id, supplied_row_version);
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "document.submit",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: SubmittedResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::OK.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok(Json(response));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let version = state
        .store
        .load_document_version(state.tenant_id, version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("document version"))?;
    let expected_row_version = match supplied_row_version {
        Some(value) => value,
        None => state
            .store
            .document_version_row_version(state.tenant_id, version_id)
            .await?
            .ok_or_else(|| ApiError::not_found("document version"))?,
    };
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("document"))?;
    let mut platform = Platform::new(state.tenant_id, development_signature_policy());
    platform
        .restore_document_version(document, version)
        .map_err(ApiError::domain)?;
    platform
        .submit_version(version_id, &state.hope_actor)
        .map_err(ApiError::domain)?;
    let updated = platform
        .version(version_id)
        .ok_or_else(|| ApiError::not_found("submitted version"))?
        .clone();
    if !unit
        .update_document_version_status_if_row_version(
            state.tenant_id,
            version_id,
            updated.status().label(),
            expected_row_version,
        )
        .await?
    {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "document version changed; refresh it and retry with a new If-Match value",
        ));
    }
    if let Some(event) = platform.audit_events().last().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = SubmittedResponse {
        version_id: version_id.to_string(),
        status: updated.status().label().to_owned(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(state.tenant_id, key, StatusCode::OK.as_u16() as i32, &body)
            .await?;
    }
    unit.commit().await?;
    Ok(Json(response))
}

async fn create_indicative_app(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(ppmp_version_id): Path<Uuid>,
    Json(request): Json<CreateIndicativeAppRequest>,
) -> Result<(StatusCode, Json<CreatePpmpResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    let ppmp_version_id = DocumentVersionId::from_uuid(ppmp_version_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    );
    let fingerprint = format!(
        "indicative-app:{}:{:?}:{}",
        ppmp_version_id, supplied_row_version, request_hash
    );
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "indicative_app.create",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: CreatePpmpResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::CREATED.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok((StatusCode::CREATED, Json(response)));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let ppmp_version = state
        .store
        .load_document_version(state.tenant_id, ppmp_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("PPMP version"))?;
    if let Some(expected) = supplied_row_version {
        let actual = state
            .store
            .document_version_row_version(state.tenant_id, ppmp_version_id)
            .await?
            .ok_or_else(|| ApiError::not_found("PPMP version"))?;
        if actual != expected {
            unit.rollback().await?;
            return Err(ApiError::conflict(
                "PPMP version changed; refresh it and retry with a new If-Match value",
            ));
        }
    }
    let ppmp_document = state
        .store
        .load_document(state.tenant_id, ppmp_version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("PPMP document"))?;
    if ppmp_document.document_type() != DocumentType::Ppmp {
        return Err(ApiError::not_found("PPMP version"));
    }

    let ppmp_content: PpmpContent = serde_json::from_value(serde_json::Value::Object(
        ppmp_version
            .content()
            .fields()
            .clone()
            .into_iter()
            .collect(),
    ))
    .map_err(|error| {
        ApiError::domain(DomainError::rule(format!(
            "persisted PPMP content could not be decoded: {error}"
        )))
    })?;
    let app_content =
        AppContent::from_ppmp(&ppmp_content, ppmp_version_id).map_err(ApiError::domain)?;
    let template = TemplateRef::new(
        request.template_id,
        request.template_version,
        request.template_authority,
    )
    .map_err(ApiError::domain)?;
    let schema =
        SchemaRef::new(request.schema_id, request.schema_version).map_err(ApiError::domain)?;
    let rule_pack_hash = ContentHash::from_hex(request.rule_pack_hash).map_err(ApiError::domain)?;
    let rule_pack = RulePackRef::new(
        RulePackSnapshotId::from_uuid(request.rule_pack_snapshot_id),
        request.rule_pack_id,
        rule_pack_hash,
    )
    .map_err(ApiError::domain)?;

    let mut platform = Platform::new(state.tenant_id, SignaturePolicy::new());
    let app_version_id = platform
        .create_indicative_app_draft(
            request.title,
            ppmp_version_id,
            app_content,
            template,
            schema,
            state.actor,
            rule_pack,
        )
        .map_err(ApiError::domain)?;
    let app_version = platform
        .version(app_version_id)
        .ok_or_else(|| ApiError::not_found("created APP version"))?
        .clone();
    let app_document = platform
        .document(app_version.document_id())
        .ok_or_else(|| ApiError::not_found("created APP document"))?
        .clone();

    unit.save_document(&app_document).await?;
    unit.save_document_version(state.tenant_id, &app_version)
        .await?;
    for event in platform.audit_events().iter().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = CreatePpmpResponse {
        document_id: app_version.document_id().to_string(),
        version_id: app_version.id().to_string(),
        content_hash: app_version.content_hash().to_string(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(
            state.tenant_id,
            key,
            StatusCode::CREATED.as_u16() as i32,
            &body,
        )
        .await?;
    }
    unit.commit().await?;

    Ok((StatusCode::CREATED, Json(response)))
}

async fn create_bac_resolution(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(app_version_id): Path<Uuid>,
    Json(request): Json<CreateBacResolutionRequest>,
) -> Result<(StatusCode, Json<BacResolutionResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    if !matches!(
        state.actor.role(),
        ActorRole::BacChairperson | ActorRole::BacViceChairperson
    ) {
        return Err(ApiError::forbidden(
            "the configured API principal is not a BAC presiding officer",
        ));
    }
    let app_version_id = DocumentVersionId::from_uuid(app_version_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    );
    let fingerprint = format!(
        "bac-resolution:{}:{:?}:{}",
        app_version_id, supplied_row_version, request_hash
    );
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "bac_resolution.create",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: BacResolutionResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::CREATED.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok((StatusCode::CREATED, Json(response)));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let version = state
        .store
        .load_document_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    if let Some(expected) = supplied_row_version {
        let actual = state
            .store
            .document_version_row_version(state.tenant_id, app_version_id)
            .await?
            .ok_or_else(|| ApiError::not_found("APP version"))?;
        if actual != expected {
            unit.rollback().await?;
            return Err(ApiError::conflict(
                "APP version changed; refresh it and retry with a new If-Match value",
            ));
        }
    }
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("APP document"))?;
    if document.document_type() != DocumentType::AnnualProcurementPlan {
        return Err(ApiError::not_found("APP version"));
    }

    let attendance: BTreeSet<UserId> = request
        .attendance
        .into_iter()
        .map(UserId::from_uuid)
        .collect();
    let votes = request
        .votes
        .into_iter()
        .map(|vote| (UserId::from_uuid(vote.user_id), vote.vote))
        .collect::<BTreeMap<_, _>>();
    let mut platform = Platform::new(state.tenant_id, SignaturePolicy::new());
    platform
        .restore_document_version(document, version)
        .map_err(ApiError::domain)?;
    let resolution_id = platform
        .bac_recommend_app(
            app_version_id,
            request.resolution_number,
            request.membership,
            attendance,
            votes,
            &state.actor,
        )
        .map_err(ApiError::domain)?;
    let resolution = platform
        .bac_resolution(resolution_id)
        .ok_or_else(|| ApiError::not_found("created BAC resolution"))?
        .clone();

    unit.save_bac_resolution(state.tenant_id, &resolution)
        .await?;
    if let Some(event) = platform.audit_events().last().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = BacResolutionResponse {
        resolution_id: resolution.id().to_string(),
        app_version_id: resolution.version_id().to_string(),
        outcome: format!("{:?}", resolution.outcome()),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(
            state.tenant_id,
            key,
            StatusCode::CREATED.as_u16() as i32,
            &body,
        )
        .await?;
    }
    unit.commit().await?;

    Ok((StatusCode::CREATED, Json(response)))
}

async fn create_hope_approval(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(app_version_id): Path<Uuid>,
    Json(request): Json<CreateHopeApprovalRequest>,
) -> Result<(StatusCode, Json<HopeApprovalResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    let app_version_id = DocumentVersionId::from_uuid(app_version_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    );
    let fingerprint = format!(
        "hope-approval:{}:{:?}:{}",
        app_version_id, supplied_row_version, request_hash
    );
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "hope_approval.create",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: HopeApprovalResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::CREATED.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok((StatusCode::CREATED, Json(response)));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let version = state
        .store
        .load_document_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    let actual_row_version = state
        .store
        .document_version_row_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    if let Some(expected) = supplied_row_version {
        if actual_row_version != expected {
            unit.rollback().await?;
            return Err(ApiError::conflict(
                "APP version changed; refresh it and retry with a new If-Match value",
            ));
        }
    }
    let expected_row_version = actual_row_version;
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("APP document"))?;
    if document.document_type() != DocumentType::AnnualProcurementPlan {
        return Err(ApiError::not_found("APP version"));
    }

    let resolutions = state
        .store
        .bac_resolutions_for_version(state.tenant_id, app_version_id)
        .await?;
    let mut platform = Platform::new(state.tenant_id, SignaturePolicy::new());
    platform
        .restore_document_version(document, version)
        .map_err(ApiError::domain)?;
    for resolution in resolutions {
        platform
            .restore_bac_resolution(resolution)
            .map_err(ApiError::domain)?;
    }
    let approval_id = platform
        .hope_approve_app(
            app_version_id,
            &state.hope_actor,
            request.authority_ref,
            request.grounds,
        )
        .map_err(ApiError::domain)?;
    let approval = platform
        .approval(approval_id)
        .ok_or_else(|| ApiError::not_found("created HoPE approval"))?
        .clone();
    let updated_version = platform
        .version(app_version_id)
        .ok_or_else(|| ApiError::not_found("updated APP version"))?
        .clone();

    if !unit
        .update_document_version_status_if_row_version(
            state.tenant_id,
            app_version_id,
            updated_version.status().label(),
            expected_row_version,
        )
        .await?
    {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "APP version changed; refresh it and retry with a new If-Match value",
        ));
    }
    unit.save_approval(state.tenant_id, &approval).await?;
    if let Some(event) = platform.audit_events().last().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = HopeApprovalResponse {
        approval_id: approval.id().to_string(),
        app_version_id: approval.version_id().to_string(),
        status: approval.status().label().to_owned(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(
            state.tenant_id,
            key,
            StatusCode::CREATED.as_u16() as i32,
            &body,
        )
        .await?;
    }
    unit.commit().await?;

    Ok((StatusCode::CREATED, Json(response)))
}

fn development_signature_policy() -> SignaturePolicy {
    SignaturePolicy::new()
        .with_rule(
            DocumentType::AnnualProcurementPlan,
            vec![ActorRole::Hope],
            vec![SignatureMethod::AdvancedElectronicSignature],
        )
        .expect("Development signature policy is valid")
}

async fn create_signature_request(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(app_version_id): Path<Uuid>,
    Json(request): Json<CreateSignatureRequest>,
) -> Result<(StatusCode, Json<SignatureResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    let app_version_id = DocumentVersionId::from_uuid(app_version_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    );
    let fingerprint = format!(
        "signature-request:{}:{:?}:{}",
        app_version_id, supplied_row_version, request_hash
    );
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "signature_request.create",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: SignatureResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::CREATED.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok((StatusCode::CREATED, Json(response)));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let version = state
        .store
        .load_document_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    let actual_row_version = state
        .store
        .document_version_row_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    if supplied_row_version.is_some_and(|expected| expected != actual_row_version) {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "APP version changed; refresh it and retry with a new If-Match value",
        ));
    }
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("APP document"))?;
    let mut platform = Platform::new(state.tenant_id, development_signature_policy());
    platform
        .restore_document_version(document, version)
        .map_err(ApiError::domain)?;
    let request_id = platform
        .request_signature(
            app_version_id,
            &state.hope_actor,
            request.purpose,
            request.method,
            request.declaration,
            parse_timestamp(&request.deadline)?,
        )
        .map_err(ApiError::domain)?;
    let signature_request = platform
        .signature_request(request_id)
        .ok_or_else(|| ApiError::not_found("created signature request"))?
        .clone();
    unit.save_signature_request(state.tenant_id, &signature_request)
        .await?;
    if let Some(event) = platform.audit_events().last().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = SignatureResponse {
        signature_request_id: request_id.to_string(),
        signature_id: None,
        app_version_id: app_version_id.to_string(),
        status: signature_request.status().label().to_owned(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(
            state.tenant_id,
            key,
            StatusCode::CREATED.as_u16() as i32,
            &body,
        )
        .await?;
    }
    unit.commit().await?;
    Ok((StatusCode::CREATED, Json(response)))
}

async fn complete_signature(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(request_id): Path<Uuid>,
    Json(request): Json<CompleteSignatureRequest>,
) -> Result<(StatusCode, Json<SignatureResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    let request_id = procuchain_shared::SignatureRequestId::from_uuid(request_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    );
    let fingerprint = format!(
        "signature-completion:{}:{:?}:{}",
        request_id, supplied_row_version, request_hash
    );
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "signature.complete",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: SignatureResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::CREATED.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok((StatusCode::CREATED, Json(response)));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let signature_request = state
        .store
        .load_signature_request(state.tenant_id, request_id)
        .await?
        .ok_or_else(|| ApiError::not_found("signature request"))?;
    let app_version_id = signature_request.version_id();
    let version = state
        .store
        .load_document_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    let actual_row_version = state
        .store
        .document_version_row_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    if supplied_row_version.is_some_and(|expected| expected != actual_row_version) {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "APP version changed; refresh it and retry with a new If-Match value",
        ));
    }
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("APP document"))?;
    let existing_signatures = state
        .store
        .signatures_for_version(state.tenant_id, app_version_id)
        .await?;
    let mut platform = Platform::new(state.tenant_id, development_signature_policy());
    platform
        .restore_document_version(document, version)
        .map_err(ApiError::domain)?;
    platform
        .restore_signature_request(signature_request)
        .map_err(ApiError::domain)?;
    for signature in existing_signatures {
        platform
            .restore_signature(signature)
            .map_err(ApiError::domain)?;
    }
    let artifact_hash =
        ContentHash::from_hex(request.signed_artifact_hash).map_err(ApiError::domain)?;
    let signature_id = platform
        .complete_signature(
            request_id,
            parse_timestamp(&request.signed_at)?,
            request.provider_transaction,
            request.credential_ref,
            artifact_hash,
            request.validation,
        )
        .map_err(ApiError::domain)?;
    let signature = platform
        .signature(signature_id)
        .ok_or_else(|| ApiError::not_found("created signature"))?
        .clone();
    let updated_request = platform
        .signature_request(request_id)
        .ok_or_else(|| ApiError::not_found("updated signature request"))?
        .clone();
    let updated_version = platform
        .version(app_version_id)
        .ok_or_else(|| ApiError::not_found("updated APP version"))?
        .clone();
    unit.save_signature_request(state.tenant_id, &updated_request)
        .await?;
    unit.save_signature(state.tenant_id, &signature).await?;
    if !unit
        .update_document_version_status_if_row_version(
            state.tenant_id,
            app_version_id,
            updated_version.status().label(),
            actual_row_version,
        )
        .await?
    {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "APP version changed; refresh it and retry with a new If-Match value",
        ));
    }
    for event in platform.audit_events().iter().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = SignatureResponse {
        signature_request_id: request_id.to_string(),
        signature_id: Some(signature_id.to_string()),
        app_version_id: app_version_id.to_string(),
        status: updated_request.status().label().to_owned(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(
            state.tenant_id,
            key,
            StatusCode::CREATED.as_u16() as i32,
            &body,
        )
        .await?;
    }
    unit.commit().await?;
    Ok((StatusCode::CREATED, Json(response)))
}

async fn issue_app(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(app_version_id): Path<Uuid>,
    Json(request): Json<IssueAppRequest>,
) -> Result<(StatusCode, Json<IssuanceResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    let app_version_id = DocumentVersionId::from_uuid(app_version_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    );
    let fingerprint = format!(
        "issuance:{}:{:?}:{}",
        app_version_id, supplied_row_version, request_hash
    );
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "issuance.create",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: IssuanceResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::CREATED.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok((StatusCode::CREATED, Json(response)));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let version = state
        .store
        .load_document_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    let actual_row_version = state
        .store
        .document_version_row_version(state.tenant_id, app_version_id)
        .await?
        .ok_or_else(|| ApiError::not_found("APP version"))?;
    if supplied_row_version.is_some_and(|expected| expected != actual_row_version) {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "APP version changed; refresh it and retry with a new If-Match value",
        ));
    }
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("APP document"))?;
    let resolutions = state
        .store
        .bac_resolutions_for_version(state.tenant_id, app_version_id)
        .await?;
    let approvals = state
        .store
        .approvals_for_version(state.tenant_id, app_version_id)
        .await?;
    let signature_requests = state
        .store
        .signature_requests_for_version(state.tenant_id, app_version_id)
        .await?;
    let signatures = state
        .store
        .signatures_for_version(state.tenant_id, app_version_id)
        .await?;
    let mut platform = Platform::new(state.tenant_id, development_signature_policy());
    platform
        .restore_document_version(document, version)
        .map_err(ApiError::domain)?;
    for resolution in resolutions {
        platform
            .restore_bac_resolution(resolution)
            .map_err(ApiError::domain)?;
    }
    for approval in approvals {
        platform
            .restore_approval(approval)
            .map_err(ApiError::domain)?;
    }
    for signature_request in signature_requests {
        platform
            .restore_signature_request(signature_request)
            .map_err(ApiError::domain)?;
    }
    for signature in signatures {
        platform
            .restore_signature(signature)
            .map_err(ApiError::domain)?;
    }
    let issuance_id = platform
        .issue(
            app_version_id,
            &state.hope_actor,
            request.authority_ref,
            request.artifacts,
            request.recipients,
        )
        .map_err(ApiError::domain)?;
    let issuance = platform
        .issuance(issuance_id)
        .ok_or_else(|| ApiError::not_found("created issuance"))?
        .clone();
    let updated_version = platform
        .version(app_version_id)
        .ok_or_else(|| ApiError::not_found("issued APP version"))?
        .clone();
    if !unit
        .update_document_version_status_if_row_version(
            state.tenant_id,
            app_version_id,
            updated_version.status().label(),
            actual_row_version,
        )
        .await?
    {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "APP version changed; refresh it and retry with a new If-Match value",
        ));
    }
    unit.save_issuance(state.tenant_id, &issuance).await?;
    for distribution_id in issuance.distribution_ids() {
        let distribution = platform
            .distribution(*distribution_id)
            .ok_or_else(|| ApiError::not_found("created distribution"))?;
        unit.save_distribution(state.tenant_id, distribution)
            .await?;
    }
    if let Some(event) = platform.audit_events().last().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = IssuanceResponse {
        issuance_id: issuance.id().to_string(),
        app_version_id: issuance.version_id().to_string(),
        distribution_ids: issuance
            .distribution_ids()
            .iter()
            .map(ToString::to_string)
            .collect(),
        manifest_hash: issuance.manifest_hash().to_string(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(
            state.tenant_id,
            key,
            StatusCode::CREATED.as_u16() as i32,
            &body,
        )
        .await?;
    }
    unit.commit().await?;
    Ok((StatusCode::CREATED, Json(response)))
}

async fn record_delivery(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(distribution_id): Path<Uuid>,
    Json(request): Json<RecordDeliveryRequest>,
) -> Result<Json<DistributionResponse>, ApiError> {
    authenticate(&state, &headers).await?;
    let distribution_id = procuchain_shared::DistributionId::from_uuid(distribution_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    );
    let fingerprint = format!(
        "delivery:{}:{:?}:{}",
        distribution_id, supplied_row_version, request_hash
    );
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "delivery.record",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: DistributionResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::OK.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok(Json(response));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let distribution = state
        .store
        .load_distribution(state.tenant_id, distribution_id)
        .await?
        .ok_or_else(|| ApiError::not_found("distribution"))?;
    let actual_row_version = state
        .store
        .distribution_row_version(state.tenant_id, distribution_id)
        .await?
        .ok_or_else(|| ApiError::not_found("distribution"))?;
    if supplied_row_version.is_some_and(|expected| expected != actual_row_version) {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "distribution changed; refresh it and retry with a new If-Match value",
        ));
    }
    let issuance = state
        .store
        .load_issuance(state.tenant_id, distribution.issuance_id())
        .await?
        .ok_or_else(|| ApiError::not_found("issuance"))?;
    let version = state
        .store
        .load_document_version(state.tenant_id, distribution.version_id())
        .await?
        .ok_or_else(|| ApiError::not_found("document version"))?;
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("document"))?;
    let mut platform = Platform::new(state.tenant_id, development_signature_policy());
    platform
        .restore_document_version(document, version)
        .map_err(ApiError::domain)?;
    platform
        .restore_issuance(issuance)
        .map_err(ApiError::domain)?;
    platform
        .restore_distribution(distribution)
        .map_err(ApiError::domain)?;
    platform
        .record_delivery(
            distribution_id,
            request.channel_ref,
            request.result,
            parse_timestamp(&request.attempted_at)?,
            &state.actor,
        )
        .map_err(ApiError::domain)?;
    let updated = platform
        .distribution(distribution_id)
        .ok_or_else(|| ApiError::not_found("updated distribution"))?;
    unit.save_distribution(state.tenant_id, updated).await?;
    if let Some(event) = platform.audit_events().last().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = DistributionResponse {
        distribution_id: distribution_id.to_string(),
        acknowledgement_id: None,
        status: updated.status().label().to_owned(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(state.tenant_id, key, StatusCode::OK.as_u16() as i32, &body)
            .await?;
    }
    unit.commit().await?;
    Ok(Json(response))
}

async fn upload_issuance_artifact(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path((issuance_id, artifact_id)): Path<(Uuid, Uuid)>,
    body: Bytes,
) -> Result<(StatusCode, Json<ArtifactUploadResponse>), ApiError> {
    authenticate(&state, &headers).await?;
    let object = state.object_store.put(&body).map_err(ApiError::storage)?;
    let mut unit = state.store.begin().await?;
    unit.link_artifact_object(
        state.tenant_id,
        procuchain_shared::IssuanceId::from_uuid(issuance_id),
        procuchain_shared::ArtifactId::from_uuid(artifact_id),
        &object,
    )
    .await?;
    unit.commit().await?;

    Ok((
        StatusCode::CREATED,
        Json(ArtifactUploadResponse {
            object_key: object.key().to_owned(),
            content_hash: object.content_hash().to_string(),
            byte_length: object.byte_length(),
        }),
    ))
}

async fn acknowledge_distribution(
    State(state): State<ApiState>,
    headers: HeaderMap,
    Path(distribution_id): Path<Uuid>,
    Json(request): Json<AcknowledgeDistributionRequest>,
) -> Result<Json<DistributionResponse>, ApiError> {
    authenticate(&state, &headers).await?;
    let distribution_id = procuchain_shared::DistributionId::from_uuid(distribution_id);
    let supplied_row_version = parse_if_match(&headers)?;
    let idempotency_key = optional_idempotency_key(&headers)?;
    let request_hash = ContentHash::sha256(
        &serde_json::to_vec(&request).map_err(|_| ApiError::internal("could not hash request"))?,
    );
    let fingerprint = format!(
        "acknowledgement:{}:{:?}:{}",
        distribution_id, supplied_row_version, request_hash
    );
    let mut unit = if let Some(key) = idempotency_key.as_deref() {
        let mut unit = state.store.begin().await?;
        match unit
            .claim_idempotency(
                state.tenant_id,
                key,
                "acknowledgement.create",
                &ContentHash::sha256(fingerprint.as_bytes()).to_string(),
            )
            .await?
        {
            IdempotencyClaim::Replay { status, body } => {
                unit.commit().await?;
                let response: DistributionResponse = serde_json::from_value(body)
                    .map_err(|_| ApiError::internal("stored idempotent response is invalid"))?;
                if status != StatusCode::OK.as_u16() as i32 {
                    return Err(ApiError::internal("stored idempotent status is invalid"));
                }
                return Ok(Json(response));
            }
            IdempotencyClaim::Conflict => {
                unit.rollback().await?;
                return Err(ApiError::conflict(
                    "Idempotency-Key is already used for a different request or is still running",
                ));
            }
            IdempotencyClaim::Owner => unit,
        }
    } else {
        state.store.begin().await?
    };
    let distribution = state
        .store
        .load_distribution(state.tenant_id, distribution_id)
        .await?
        .ok_or_else(|| ApiError::not_found("distribution"))?;
    let actual_row_version = state
        .store
        .distribution_row_version(state.tenant_id, distribution_id)
        .await?
        .ok_or_else(|| ApiError::not_found("distribution"))?;
    if supplied_row_version.is_some_and(|expected| expected != actual_row_version) {
        unit.rollback().await?;
        return Err(ApiError::conflict(
            "distribution changed; refresh it and retry with a new If-Match value",
        ));
    }
    let issuance = state
        .store
        .load_issuance(state.tenant_id, distribution.issuance_id())
        .await?
        .ok_or_else(|| ApiError::not_found("issuance"))?;
    let version = state
        .store
        .load_document_version(state.tenant_id, distribution.version_id())
        .await?
        .ok_or_else(|| ApiError::not_found("document version"))?;
    let document = state
        .store
        .load_document(state.tenant_id, version.document_id())
        .await?
        .ok_or_else(|| ApiError::not_found("document"))?;
    let mut platform = Platform::new(state.tenant_id, development_signature_policy());
    platform
        .restore_document_version(document, version)
        .map_err(ApiError::domain)?;
    platform
        .restore_issuance(issuance)
        .map_err(ApiError::domain)?;
    platform
        .restore_distribution(distribution)
        .map_err(ApiError::domain)?;
    let artifact_hash = ContentHash::from_hex(request.artifact_hash).map_err(ApiError::domain)?;
    let acknowledgement_id = platform
        .acknowledge(
            distribution_id,
            procuchain_shared::RecipientId::from_uuid(request.recipient_id),
            artifact_hash,
            request.method,
            request.declaration,
            parse_timestamp(&request.acknowledged_at)?,
        )
        .map_err(ApiError::domain)?;
    let updated = platform
        .distribution(distribution_id)
        .ok_or_else(|| ApiError::not_found("updated distribution"))?;
    unit.save_distribution(state.tenant_id, updated).await?;
    let acknowledgement = platform
        .acknowledgements()
        .get(&acknowledgement_id)
        .ok_or_else(|| ApiError::not_found("created acknowledgement"))?;
    unit.save_acknowledgement(state.tenant_id, acknowledgement)
        .await?;
    if let Some(event) = platform.audit_events().last().cloned() {
        unit.append_audit_event(
            state.tenant_id,
            event.event_type,
            event.actor,
            event.subject_kind,
            event.subject_id,
            event.occurred_at,
            event.details,
        )
        .await?;
    }
    let response = DistributionResponse {
        distribution_id: distribution_id.to_string(),
        acknowledgement_id: Some(acknowledgement_id.to_string()),
        status: updated.status().label().to_owned(),
    };
    if let Some(key) = idempotency_key.as_deref() {
        let body = serde_json::to_value(&response)
            .map_err(|_| ApiError::internal("could not store idempotent response"))?;
        unit.complete_idempotency(state.tenant_id, key, StatusCode::OK.as_u16() as i32, &body)
            .await?;
    }
    unit.commit().await?;
    Ok(Json(response))
}

fn parse_timestamp(value: &str) -> Result<OffsetDateTime, ApiError> {
    OffsetDateTime::parse(value, &Rfc3339).map_err(|error| {
        ApiError::domain(DomainError::rule(format!(
            "invalid RFC3339 timestamp: {error}"
        )))
    })
}

fn optional_idempotency_key(headers: &HeaderMap) -> Result<Option<String>, ApiError> {
    let key = headers
        .get("idempotency-key")
        .map(|value| {
            value
                .to_str()
                .map(str::trim)
                .map(str::to_owned)
                .map_err(|_| ApiError::bad_request("Idempotency-Key must be valid ASCII"))
        })
        .transpose()?
        .filter(|value| !value.is_empty());
    if key.as_ref().is_some_and(|value| value.len() > 200) {
        return Err(ApiError::bad_request(
            "Idempotency-Key must be at most 200 characters",
        ));
    }
    Ok(key)
}

fn parse_if_match(headers: &HeaderMap) -> Result<Option<i64>, ApiError> {
    let Some(value) = headers.get("if-match") else {
        return Ok(None);
    };
    let value = value
        .to_str()
        .map_err(|_| ApiError::bad_request("If-Match must be valid ASCII"))?
        .trim();
    let value = value.strip_prefix("W/").unwrap_or(value).trim_matches('"');
    value
        .parse::<i64>()
        .map(Some)
        .map_err(|_| ApiError::bad_request("If-Match must contain a numeric row version"))
}

async fn authenticate(state: &ApiState, headers: &HeaderMap) -> Result<(), ApiError> {
    authenticated_identity(state, headers).await.map(|_| ())
}

async fn authenticated_identity(
    state: &ApiState,
    headers: &HeaderMap,
) -> Result<procuchain_postgres::identity::UserIdentity, ApiError> {
    let supplied = session_cookie(headers).ok_or_else(ApiError::unauthorized)?;
    let identity = state
        .store
        .resolve_session(state.tenant_id, supplied, OffsetDateTime::now_utc())
        .await?
        .ok_or_else(ApiError::unauthorized)?;
    if !identity.active {
        return Err(ApiError::unauthorized());
    }
    if !identity.roles.contains(&state.actor.role())
        && !identity.roles.contains(&state.hope_actor.role())
    {
        return Err(ApiError::forbidden(
            "the authenticated user has no assigned API role",
        ));
    }
    Ok(identity)
}

fn bearer_token(headers: &HeaderMap) -> Option<&str> {
    headers
        .get("authorization")
        .and_then(|value| value.to_str().ok())
        .and_then(|value| value.strip_prefix("Bearer "))
        .filter(|value| !value.is_empty())
}

fn session_cookie(headers: &HeaderMap) -> Option<&str> {
    headers
        .get("cookie")
        .and_then(|value| value.to_str().ok())
        .and_then(|cookies| {
            cookies.split(';').find_map(|cookie| {
                let (name, value) = cookie.trim().split_once('=')?;
                (name == SESSION_COOKIE && !value.is_empty()).then_some(value)
            })
        })
}

#[derive(Debug, Serialize)]
struct ErrorResponse {
    code: &'static str,
    message: String,
    correlation_id: String,
}

#[derive(Debug)]
struct ApiError {
    status: StatusCode,
    code: &'static str,
    message: String,
    correlation_id: String,
}

impl ApiError {
    fn domain(error: DomainError) -> Self {
        Self {
            status: StatusCode::UNPROCESSABLE_ENTITY,
            code: "validation_error",
            message: error.to_string(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }

    fn validation(message: impl Into<String>) -> Self {
        Self {
            status: StatusCode::UNPROCESSABLE_ENTITY,
            code: "validation_error",
            message: message.into(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }

    fn not_found(resource: &'static str) -> Self {
        Self {
            status: StatusCode::NOT_FOUND,
            code: "not_found",
            message: format!("{resource} was not found"),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }

    fn unauthorized() -> Self {
        Self {
            status: StatusCode::UNAUTHORIZED,
            code: "unauthorized",
            message: "a valid bearer token is required".to_owned(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }

    fn forbidden(message: &str) -> Self {
        Self {
            status: StatusCode::FORBIDDEN,
            code: "forbidden",
            message: message.to_owned(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }

    fn bad_request(message: &str) -> Self {
        Self {
            status: StatusCode::BAD_REQUEST,
            code: "bad_request",
            message: message.to_owned(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }

    fn conflict(message: &str) -> Self {
        Self {
            status: StatusCode::CONFLICT,
            code: "conflict",
            message: message.to_owned(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }

    fn internal(message: &str) -> Self {
        Self {
            status: StatusCode::INTERNAL_SERVER_ERROR,
            code: "internal_error",
            message: message.to_owned(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }

    fn storage(error: StorageError) -> Self {
        Self {
            status: StatusCode::INTERNAL_SERVER_ERROR,
            code: "storage_error",
            message: error.to_string(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }
}

impl From<StoreError> for ApiError {
    fn from(error: StoreError) -> Self {
        Self {
            status: StatusCode::INTERNAL_SERVER_ERROR,
            code: "storage_error",
            message: error.to_string(),
            correlation_id: Uuid::now_v7().to_string(),
        }
    }
}

impl IntoResponse for ApiError {
    fn into_response(self) -> Response {
        (
            self.status,
            Json(ErrorResponse {
                code: self.code,
                message: self.message,
                correlation_id: self.correlation_id.clone(),
            }),
        )
            .into_response()
            .map_response_headers(self.correlation_id)
    }
}

trait ResponseCorrelation {
    fn map_response_headers(self, correlation_id: String) -> Response;
}

impl ResponseCorrelation for Response {
    fn map_response_headers(mut self, correlation_id: String) -> Response {
        if let Ok(value) = HeaderValue::from_str(&correlation_id) {
            self.headers_mut().insert("x-correlation-id", value);
        }
        self
    }
}

#[cfg(test)]
mod tests {
    use std::time::Duration;

    use super::{ApiState, router, router_with_state};
    use axum::{
        body::Body,
        http::{HeaderValue, Request, StatusCode},
        response::IntoResponse,
    };
    use procuchain_postgres::PgStore;
    use procuchain_shared::{Actor, ActorRole, ContentHash, TenantId, UserId};
    use serde_json::{Value, json};
    use tower::ServiceExt;
    use uuid::Uuid;

    const OPENAPI: &str = include_str!("../openapi.json");

    async fn state() -> Option<ApiState> {
        let Ok(database_url) = std::env::var("PROCUCHAIN_TEST_DATABASE_URL") else {
            eprintln!("skipping API persistence test: PROCUCHAIN_TEST_DATABASE_URL is not set");
            return None;
        };
        let store = PgStore::connect(&database_url)
            .await
            .expect("test database is reachable");
        store.migrate().await.expect("test migrations apply");
        let tenant_id = TenantId::from_uuid(
            Uuid::parse_str("018f0e2e-6f7a-7b6c-8d5e-4c3b2a190099")
                .expect("test tenant ID is valid"),
        );
        let actor_id = Uuid::parse_str("018f0e2e-6f7a-7b6c-8d5e-4c3b2a190098")
            .expect("test actor ID is valid");
        sqlx::query("INSERT INTO tenants (id, name) VALUES ($1, $2) ON CONFLICT (id) DO NOTHING")
            .bind(tenant_id.as_uuid())
            .bind("API Test Municipality")
            .execute(store.pool())
            .await
            .expect("test tenant exists");
        sqlx::query(
            "INSERT INTO users (id, tenant_id, display_name) VALUES ($1, $2, $3) ON CONFLICT (tenant_id, id) DO NOTHING",
        )
        .bind(actor_id)
        .bind(tenant_id.as_uuid())
        .bind("Development BAC Chairperson")
        .execute(store.pool())
        .await
        .expect("test actor exists");
        for role in ["bac_chairperson", "hope"] {
            sqlx::query(
                "INSERT INTO user_roles (tenant_id, user_id, role) VALUES ($1, $2, $3) ON CONFLICT DO NOTHING",
            )
            .bind(tenant_id.as_uuid())
            .bind(actor_id)
            .bind(role)
            .execute(store.pool())
        .await
        .expect("test actor role exists");
        }
        let committee_id = Uuid::parse_str("018f0e2e-6f7a-7b6c-8d5e-4c3b2a190090")
            .expect("test committee ID is valid");
        sqlx::query(
            "INSERT INTO committees (id, tenant_id, name) VALUES ($1, $2, $3) ON CONFLICT (tenant_id, id) DO NOTHING",
        )
        .bind(committee_id)
        .bind(tenant_id.as_uuid())
        .bind("Development BAC")
        .execute(store.pool())
        .await
        .expect("test committee exists");
        for (index, user_id) in [
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190091",
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190092",
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190093",
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190094",
        ]
        .into_iter()
        .enumerate()
        {
            let user_id = Uuid::parse_str(user_id).expect("test BAC user ID is valid");
            sqlx::query(
                "INSERT INTO users (id, tenant_id, display_name) VALUES ($1, $2, $3) ON CONFLICT (tenant_id, id) DO NOTHING",
            )
            .bind(user_id)
            .bind(tenant_id.as_uuid())
            .bind(format!("Development BAC Member {index}"))
            .execute(store.pool())
            .await
            .expect("test BAC user exists");
        }
        for (user_id, seat) in [
            (actor_id, "chairperson"),
            (
                Uuid::parse_str("018f0e2e-6f7a-7b6c-8d5e-4c3b2a190091").expect("member ID"),
                "vice_chairperson",
            ),
            (
                Uuid::parse_str("018f0e2e-6f7a-7b6c-8d5e-4c3b2a190092").expect("member ID"),
                "member",
            ),
            (
                Uuid::parse_str("018f0e2e-6f7a-7b6c-8d5e-4c3b2a190093").expect("member ID"),
                "member",
            ),
            (
                Uuid::parse_str("018f0e2e-6f7a-7b6c-8d5e-4c3b2a190094").expect("member ID"),
                "member",
            ),
        ] {
            sqlx::query(
                "INSERT INTO committee_memberships (tenant_id, committee_id, user_id, seat) VALUES ($1, $2, $3, $4) ON CONFLICT (tenant_id, committee_id, user_id) DO UPDATE SET seat = EXCLUDED.seat, active = true",
            )
            .bind(tenant_id.as_uuid())
            .bind(committee_id)
            .bind(user_id)
            .bind(seat)
            .execute(store.pool())
            .await
            .expect("test committee membership exists");
        }
        sqlx::query(
            "INSERT INTO api_tokens (id, tenant_id, user_id, token_hash) VALUES ($1, $2, $3, $4) ON CONFLICT (token_hash) DO NOTHING",
        )
        .bind(
            Uuid::parse_str("018f0e2e-6f7a-7b6c-8d5e-4c3b2a190097")
                .expect("test token ID is valid"),
        )
        .bind(tenant_id.as_uuid())
        .bind(actor_id)
        .bind(ContentHash::sha256(b"procuchain-development-token").as_str())
        .execute(store.pool())
        .await
        .expect("test API token exists");
        Some(ApiState::new(
            store,
            tenant_id,
            Actor::new(
                UserId::from_uuid(actor_id),
                ActorRole::BacChairperson,
                "Development BAC Chairperson",
            ),
        ))
    }

    async fn session_cookie(state: &ApiState) -> String {
        let response = router_with_state(state.clone())
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/v1/auth/session")
                    .header("authorization", "Bearer procuchain-development-token")
                    .body(Body::empty())
                    .expect("session request is valid"),
            )
            .await
            .expect("session route responds");
        assert_eq!(response.status(), StatusCode::CREATED);
        response
            .headers()
            .get("set-cookie")
            .expect("session response has a cookie")
            .to_str()
            .expect("session cookie is valid ASCII")
            .split(';')
            .next()
            .expect("session cookie has a value")
            .to_owned()
    }

    #[tokio::test]
    async fn health_endpoint_is_versioned_and_machine_readable() {
        let response = router()
            .oneshot(
                Request::builder()
                    .uri("/api/v1/health")
                    .header("x-correlation-id", "test-correlation-123")
                    .body(Body::empty())
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");

        assert_eq!(response.status(), 200);
        assert_eq!(
            response
                .headers()
                .get("x-correlation-id")
                .expect("response has a correlation ID"),
            "test-correlation-123"
        );
        assert_eq!(
            response.headers().get("x-content-type-options"),
            Some(&HeaderValue::from_static("nosniff"))
        );
        assert_eq!(
            response.headers().get("x-frame-options"),
            Some(&HeaderValue::from_static("DENY"))
        );
        let body = axum::body::to_bytes(response.into_body(), usize::MAX)
            .await
            .expect("response body is readable");
        assert_eq!(
            body,
            r#"{"status":"ok","service":"procuchain","api_version":"v1"}"#
        );
    }

    #[tokio::test]
    async fn rust_serves_the_workbench_shell_without_laravel() {
        let response = router()
            .oneshot(
                Request::builder()
                    .uri("/workbench")
                    .body(Body::empty())
                    .expect("workbench request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = axum::body::to_bytes(response.into_body(), usize::MAX)
            .await
            .expect("workbench body is readable");
        let body = String::from_utf8(body.to_vec()).expect("workbench body is UTF-8");
        assert!(body.contains("data-page"));
        assert!(body.contains("workbench"));
    }

    #[tokio::test]
    async fn sessions_replace_direct_bearer_access_and_can_be_revoked() {
        let Some(state) = state().await else {
            return;
        };
        let session = session_cookie(&state).await;
        let response = router_with_state(state.clone())
            .oneshot(
                Request::builder()
                    .uri("/api/v1/me")
                    .header("cookie", &session)
                    .body(Body::empty())
                    .expect("session request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::OK);

        let bearer_response = router_with_state(state.clone())
            .oneshot(
                Request::builder()
                    .uri("/api/v1/me")
                    .header("authorization", "Bearer procuchain-development-token")
                    .body(Body::empty())
                    .expect("bearer request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(bearer_response.status(), StatusCode::UNAUTHORIZED);

        let logout = router_with_state(state.clone())
            .oneshot(
                Request::builder()
                    .method("DELETE")
                    .uri("/api/v1/auth/session")
                    .header("cookie", &session)
                    .body(Body::empty())
                    .expect("logout request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(logout.status(), StatusCode::NO_CONTENT);

        let revoked_response = router_with_state(state)
            .oneshot(
                Request::builder()
                    .uri("/api/v1/me")
                    .header("cookie", &session)
                    .body(Body::empty())
                    .expect("revoked session request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(revoked_response.status(), StatusCode::UNAUTHORIZED);
    }

    #[tokio::test]
    async fn workflow_task_api_supports_create_claim_complete_and_read() {
        let Some(state) = state().await else {
            return;
        };
        let session = session_cookie(&state).await;
        let response = router_with_state(state.clone())
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/v1/workflow/tasks")
                    .header("content-type", "application/json")
                    .header("cookie", &session)
                    .body(Body::from(
                        r#"{"task_type":"ppmp_review","subject_ref":"ppmp:acceptance"}"#,
                    ))
                    .expect("task create request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::CREATED);
        let body = axum::body::to_bytes(response.into_body(), usize::MAX)
            .await
            .expect("body");
        let task: Value = serde_json::from_slice(&body).expect("task response is JSON");
        let task_id = task["id"].as_str().expect("task ID");
        assert_eq!(task["status"], "pending");

        let response = router_with_state(state.clone())
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/v1/workflow/tasks/{task_id}/claim"))
                    .header("cookie", &session)
                    .body(Body::empty())
                    .expect("task claim request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::OK);

        let response = router_with_state(state.clone())
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/v1/workflow/tasks/{task_id}/complete"))
                    .header("content-type", "application/json")
                    .header("cookie", &session)
                    .body(Body::from(r#"{"outcome":"validated"}"#))
                    .expect("task completion request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::OK);

        let response = router_with_state(state)
            .oneshot(
                Request::builder()
                    .uri(format!("/api/v1/workflow/tasks/{task_id}"))
                    .header("cookie", &session)
                    .body(Body::empty())
                    .expect("task read request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = axum::body::to_bytes(response.into_body(), usize::MAX)
            .await
            .expect("body");
        let task: Value = serde_json::from_slice(&body).expect("task response is JSON");
        assert_eq!(task["status"], "completed");
        assert_eq!(task["outcome"], "validated");
    }

    #[test]
    fn rate_limiter_rejects_requests_after_the_configured_window_budget() {
        let limiter = super::RateLimiter::new(1, Duration::from_secs(60));
        assert!(limiter.allow("test-client"));
        assert!(!limiter.allow("test-client"));
        assert!(limiter.allow("different-client"));
    }

    #[test]
    fn ppmp_template_validation_rejects_unregistered_metadata() {
        let request: super::CreatePpmpRequest = serde_json::from_value(json!({
            "title": "PPMP",
            "content": {
                "fiscal_year": 2026,
                "end_user_unit": "General Services",
                "project_title": "Office supplies",
                "scope": "Common office supplies",
                "market_scoping_ref": "MS-001",
                "abc_basis": "Recent canvass",
                "schedule": "Q1 2026",
                "risks": [],
                "lines": [{
                    "line_id": "1",
                    "description": "Bond paper",
                    "quantity": 10,
                    "unit": "ream",
                    "estimated_cost": {"centavos": 25000, "currency": "Php"},
                    "schedule": "January 2026",
                    "mode_consideration": "Small Value Procurement"
                }]
            },
            "template_id": "unregistered-template",
            "template_version": "2026.1",
            "template_authority": "PE template register",
            "schema_id": "ppmp-schema",
            "schema_version": "1.0",
            "rule_pack_snapshot_id": "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190001",
            "rule_pack_id": "rp-2026-a",
            "rule_pack_hash": "6f1ed002ab5595859014ebf0951522d7a5a3fdd1b2f3c7d7a8a0d8c6e1d6a5a5"
        }))
        .expect("test request is valid JSON");
        let error =
            super::validate_ppmp_request(&request).expect_err("template must be registered");
        assert_eq!(error.code, "validation_error");
        assert!(error.message.contains("registered ppmp-template"));
    }

    #[test]
    fn openapi_contract_covers_the_versioned_router_surface() {
        let document: Value =
            serde_json::from_str(OPENAPI).expect("OpenAPI contract is valid JSON");
        assert_eq!(document["openapi"], "3.1.0");
        assert_eq!(document["servers"][0]["url"], "/api/v1");
        assert_eq!(
            document["security"][0]["sessionCookie"]
                .as_array()
                .map(Vec::len),
            Some(0)
        );
        assert_eq!(
            document["components"]["securitySchemes"]["sessionCookie"]["in"],
            "cookie"
        );

        let paths = document["paths"].as_object().expect("paths are an object");
        for path in [
            "/auth/session",
            "/workflow/tasks",
            "/workflow/tasks/{task_id}",
            "/workflow/tasks/{task_id}/claim",
            "/workflow/tasks/{task_id}/complete",
            "/health",
            "/me",
            "/committees/{committee_id}/members",
            "/ppmp",
            "/ppmp/{version_id}",
            "/ppmp/{version_id}/indicative-app",
            "/app/{version_id}/bac-resolution",
            "/app/{version_id}/hope-approval",
            "/app/{version_id}/signature-request",
            "/signature-requests/{request_id}/complete",
            "/app/{version_id}/issue",
            "/issuances/{issuance_id}/artifacts/{artifact_id}",
            "/documents/{version_id}/submit",
            "/distributions/{distribution_id}/deliver",
            "/distributions/{distribution_id}/acknowledge",
        ] {
            assert!(paths.contains_key(path), "OpenAPI is missing {path}");
        }

        assert_eq!(
            document["components"]["schemas"]["ErrorResponse"]["required"],
            json!(["code", "message", "correlation_id"])
        );

        for schema in [
            "CreatePpmpRequest",
            "CreateIndicativeAppRequest",
            "CreateBacResolutionRequest",
            "CreateHopeApprovalRequest",
            "CreateSignatureRequest",
            "CompleteSignatureRequest",
            "IssueAppRequest",
            "RecordDeliveryRequest",
            "AcknowledgeDistributionRequest",
            "CreatePpmpResponse",
            "BacResolutionResponse",
            "HopeApprovalResponse",
            "SignatureResponse",
            "IssuanceResponse",
            "DistributionResponse",
            "ArtifactUploadResponse",
            "CreateWorkflowTaskRequest",
            "CompleteWorkflowTaskRequest",
            "WorkflowTaskResponse",
        ] {
            assert!(
                document["components"]["schemas"].get(schema).is_some(),
                "OpenAPI is missing schema {schema}"
            );
        }

        let upload = &paths["/issuances/{issuance_id}/artifacts/{artifact_id}"]["post"];
        assert_eq!(
            upload["requestBody"]["content"]["application/octet-stream"]["schema"]["format"],
            "binary"
        );
        assert_eq!(
            upload["responses"]["201"]["content"]["application/json"]["schema"]["$ref"],
            "#/components/schemas/ArtifactUploadResponse"
        );

        for (path, request_schema, response_code, response_schema) in [
            ("/ppmp", "CreatePpmpRequest", "201", "CreatePpmpResponse"),
            (
                "/ppmp/{version_id}/indicative-app",
                "CreateIndicativeAppRequest",
                "201",
                "CreatePpmpResponse",
            ),
            (
                "/app/{version_id}/bac-resolution",
                "CreateBacResolutionRequest",
                "201",
                "BacResolutionResponse",
            ),
            (
                "/app/{version_id}/hope-approval",
                "CreateHopeApprovalRequest",
                "201",
                "HopeApprovalResponse",
            ),
            (
                "/app/{version_id}/signature-request",
                "CreateSignatureRequest",
                "201",
                "SignatureResponse",
            ),
            (
                "/signature-requests/{request_id}/complete",
                "CompleteSignatureRequest",
                "201",
                "SignatureResponse",
            ),
            (
                "/app/{version_id}/issue",
                "IssueAppRequest",
                "201",
                "IssuanceResponse",
            ),
            (
                "/distributions/{distribution_id}/deliver",
                "RecordDeliveryRequest",
                "200",
                "DistributionResponse",
            ),
            (
                "/distributions/{distribution_id}/acknowledge",
                "AcknowledgeDistributionRequest",
                "200",
                "DistributionResponse",
            ),
        ] {
            let operation = &paths[path]["post"];
            assert_eq!(
                operation["requestBody"]["content"]["application/json"]["schema"]["$ref"],
                format!("#/components/schemas/{request_schema}")
            );
            assert_eq!(
                operation["responses"][response_code]["content"]["application/json"]["schema"]["$ref"],
                format!("#/components/schemas/{response_schema}")
            );
        }
    }

    #[test]
    fn api_errors_include_a_matching_correlation_id() {
        let response = super::ApiError::unauthorized().into_response();
        let header = response
            .headers()
            .get("x-correlation-id")
            .expect("error has a correlation ID")
            .to_str()
            .expect("correlation ID is valid ASCII");
        assert!(!header.is_empty());
    }

    #[tokio::test]
    async fn unversioned_health_endpoint_is_not_exposed() {
        let response = router()
            .oneshot(
                Request::builder()
                    .uri("/health")
                    .body(Body::empty())
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");

        assert_eq!(response.status(), 404);
    }

    #[tokio::test]
    async fn ppmp_creation_returns_created_version_metadata() {
        let Some(created_state) = state().await else {
            return;
        };
        let created_session = session_cookie(&created_state).await;
        let response = router_with_state(created_state)
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/v1/ppmp")
                    .header("content-type", "application/json")
                    .header("cookie", &created_session)
                    .body(Body::from(
                        r#"{
                            "title":"PPMP FY 2026",
                            "content":{
                                "fiscal_year":2026,
                                "end_user_unit":"General Services",
                                "project_title":"Office supplies",
                                "scope":"Common office supplies",
                                "market_scoping_ref":"MS-2026-001",
                                "abc_basis":"Recent canvass",
                                "schedule":"Q1 2026",
                                "risks":[],
                                "lines":[{
                                    "line_id":"1",
                                    "description":"Bond paper",
                                    "quantity":10,
                                    "unit":"ream",
                                    "estimated_cost":{"centavos":25000,"currency":"Php"},
                                    "schedule":"January 2026",
                                    "mode_consideration":"Small Value Procurement"
                                }]
                            },
                            "template_id":"ppmp-template",
                            "template_version":"2026.1",
                            "template_authority":"PE template register",
                            "schema_id":"ppmp-schema",
                            "schema_version":"1.0",
                            "rule_pack_snapshot_id":"018f0e2e-6f7a-7b6c-8d5e-4c3b2a190001",
                            "rule_pack_id":"rp-2026-a",
                            "rule_pack_hash":"6f1ed002ab5595859014ebf0951522d7a5a3fdd1b2f3c7d7a8a0d8c6e1d6a5a5"
                        }"#,
                    ))
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");

        assert_eq!(response.status(), StatusCode::CREATED);
        let body = axum::body::to_bytes(response.into_body(), usize::MAX)
            .await
            .expect("response body is readable");
        let created: serde_json::Value = serde_json::from_slice(&body).expect("valid JSON");
        let version_id = created["version_id"]
            .as_str()
            .expect("version ID is present");

        let Some(restarted_state) = state().await else {
            return;
        };
        let restarted_session = session_cookie(&restarted_state).await;
        let response = router_with_state(restarted_state)
            .oneshot(
                Request::builder()
                    .uri(format!("/api/v1/ppmp/{version_id}"))
                    .header("cookie", &restarted_session)
                    .body(Body::empty())
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = axum::body::to_bytes(response.into_body(), usize::MAX)
            .await
            .expect("response body is readable");
        let retrieved: serde_json::Value = serde_json::from_slice(&body).expect("valid JSON");
        assert_eq!(retrieved["version_id"], version_id);
        assert_eq!(retrieved["content"]["fiscal_year"], 2026);
        assert!(retrieved["content_hash"].as_str().is_some());

        let Some(app_state) = state().await else {
            return;
        };
        let app_session = session_cookie(&app_state).await;
        let response = router_with_state(app_state)
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/v1/ppmp/{version_id}/indicative-app"))
                    .header("content-type", "application/json")
                    .header("cookie", &app_session)
                    .body(Body::from(
                        r#"{
                            "title":"Indicative APP FY 2026",
                            "template_id":"app-template",
                            "template_version":"2026.1",
                            "template_authority":"PE template register",
                            "schema_id":"app-schema",
                            "schema_version":"1.0",
                            "rule_pack_snapshot_id":"018f0e2e-6f7a-7b6c-8d5e-4c3b2a190001",
                            "rule_pack_id":"rp-2026-a",
                            "rule_pack_hash":"6f1ed002ab5595859014ebf0951522d7a5a3fdd1b2f3c7d7a8a0d8c6e1d6a5a5"
                        }"#,
                    ))
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::CREATED);
    }

    #[tokio::test]
    async fn invalid_ppmp_is_rejected_without_a_created_response() {
        let Some(state) = state().await else {
            return;
        };
        let session = session_cookie(&state).await;
        let response = router_with_state(state)
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/v1/ppmp")
                    .header("content-type", "application/json")
                    .header("cookie", &session)
                    .body(Body::from("{\"title\":\"invalid\"}"))
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");

        assert_eq!(response.status(), StatusCode::UNPROCESSABLE_ENTITY);
    }

    #[tokio::test]
    async fn ppmp_routes_require_authentication() {
        let Some(state) = state().await else {
            return;
        };
        let response = router_with_state(state)
            .oneshot(
                Request::builder()
                    .uri("/api/v1/ppmp/018f0e2e-6f7a-7b6c-8d5e-4c3b2a190001")
                    .body(Body::empty())
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");

        assert_eq!(response.status(), StatusCode::UNAUTHORIZED);
    }

    #[tokio::test]
    async fn committee_members_are_loaded_from_postgres_for_authenticated_users() {
        let Some(state) = state().await else {
            return;
        };
        let session = session_cookie(&state).await;
        let response = router_with_state(state)
            .oneshot(
                Request::builder()
                    .uri("/api/v1/committees/018f0e2e-6f7a-7b6c-8d5e-4c3b2a190090/members")
                    .header("cookie", &session)
                    .body(Body::empty())
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");
        assert_eq!(response.status(), StatusCode::OK);
        let body = axum::body::to_bytes(response.into_body(), usize::MAX)
            .await
            .expect("response body is readable");
        let members: Vec<Value> = serde_json::from_slice(&body).expect("members are JSON");
        assert_eq!(members.len(), 5);
        assert!(members.iter().any(|member| member["seat"] == "chairperson"));
    }

    async fn post_json(state: ApiState, path: String, payload: Value) -> (StatusCode, Value) {
        let session = session_cookie(&state).await;
        let response = router_with_state(state)
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(path)
                    .header("content-type", "application/json")
                    .header("cookie", &session)
                    .body(Body::from(payload.to_string()))
                    .expect("request is valid"),
            )
            .await
            .expect("router responds");
        let status = response.status();
        let body = axum::body::to_bytes(response.into_body(), usize::MAX)
            .await
            .expect("response body is readable");
        (
            status,
            serde_json::from_slice(&body).unwrap_or_else(|error| {
                panic!(
                    "response is JSON ({error}): {}",
                    String::from_utf8_lossy(&body)
                )
            }),
        )
    }

    #[tokio::test]
    async fn ac120_api_completes_from_ppmp_through_acknowledgement() {
        let Some(state) = state().await else {
            return;
        };
        let rule_hash = "6f1ed002ab5595859014ebf0951522d7a5a3fdd1b2f3c7d7a8a0d8c6e1d6a5a5";
        let common = json!({
            "template_id":"ppmp-template", "template_version":"2026.1", "template_authority":"PE template register",
            "schema_id":"ppmp-schema", "schema_version":"1.0",
            "rule_pack_snapshot_id":"018f0e2e-6f7a-7b6c-8d5e-4c3b2a190001", "rule_pack_id":"rp-2026-a", "rule_pack_hash":rule_hash
        });
        let mut ppmp = common.clone();
        ppmp["title"] = json!("AC-120 PPMP FY 2026");
        ppmp["content"] = json!({
            "fiscal_year":2026, "end_user_unit":"General Services", "project_title":"Office supplies",
            "scope":"Common office supplies", "market_scoping_ref":"MS-2026-001", "abc_basis":"Recent canvass",
            "schedule":"Q1 2026", "risks":[], "lines":[{"line_id":"1","description":"Bond paper","quantity":10,"unit":"ream","estimated_cost":{"centavos":25000,"currency":"Php"},"schedule":"January 2026","mode_consideration":"Small Value Procurement"}]
        });
        let (status, created) = post_json(state.clone(), "/api/v1/ppmp".to_owned(), ppmp).await;
        assert_eq!(status, StatusCode::CREATED, "{created}");
        let ppmp_id = created["version_id"].as_str().expect("PPMP ID");

        let app = json!({"title":"AC-120 Indicative APP FY 2026", "template_id":"app-template", "template_version":"2026.1", "template_authority":"PE template register", "schema_id":"app-schema", "schema_version":"1.0", "rule_pack_snapshot_id":"018f0e2e-6f7a-7b6c-8d5e-4c3b2a190001", "rule_pack_id":"rp-2026-a", "rule_pack_hash":rule_hash});
        let (status, created) = post_json(
            state.clone(),
            format!("/api/v1/ppmp/{ppmp_id}/indicative-app"),
            app,
        )
        .await;
        assert_eq!(status, StatusCode::CREATED, "{created}");
        let app_id = created["version_id"].as_str().expect("APP ID");
        let (status, _) = post_json(
            state.clone(),
            format!("/api/v1/documents/{app_id}/submit"),
            json!({}),
        )
        .await;
        assert_eq!(status, StatusCode::OK);

        let ids = [
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190098",
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190091",
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190092",
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190093",
            "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190094",
        ];
        let bac = json!({"resolution_number":"BAC-AC120-001","membership":{"committee_id":"018f0e2e-6f7a-7b6c-8d5e-4c3b2a190090","members":[{"user_id":ids[0],"seat":"Chairperson","display_name":"Development BAC Chairperson"},{"user_id":ids[1],"seat":"ViceChairperson","display_name":"Development BAC Member 0"},{"user_id":ids[2],"seat":"Member","display_name":"Development BAC Member 1"},{"user_id":ids[3],"seat":"Member","display_name":"Development BAC Member 2"},{"user_id":ids[4],"seat":"Member","display_name":"Development BAC Member 3"}]},"attendance":ids,"votes":[{"user_id":ids[1],"vote":"Recommend"},{"user_id":ids[2],"vote":"Recommend"},{"user_id":ids[3],"vote":"Recommend"},{"user_id":ids[4],"vote":"Recommend"}]});
        let (status, bac_result) = post_json(
            state.clone(),
            format!("/api/v1/app/{app_id}/bac-resolution"),
            bac,
        )
        .await;
        assert_eq!(status, StatusCode::CREATED, "{bac_result}");
        let (status, hope) = post_json(
            state.clone(),
            format!("/api/v1/app/{app_id}/hope-approval"),
            json!({"authority_ref":"HOPE-AC120-001","grounds":"BAC recommendation accepted"}),
        )
        .await;
        assert_eq!(status, StatusCode::CREATED, "{hope}");
        let (status, signature_request) = post_json(state.clone(), format!("/api/v1/app/{app_id}/signature-request"), json!({"purpose":"Approval","method":"AdvancedElectronicSignature","declaration":"I approve this APP","deadline":"2030-02-01T00:00:00Z"})).await;
        assert_eq!(status, StatusCode::CREATED, "{signature_request}");
        let request_id = signature_request["signature_request_id"]
            .as_str()
            .expect("signature request ID");
        let (status, signed) = post_json(state.clone(), format!("/api/v1/signature-requests/{request_id}/complete"), json!({"signed_at":"2030-01-02T00:00:00Z","provider_transaction":"fake-tx-ac120","credential_ref":"fake-credential","signed_artifact_hash":created["content_hash"],"validation":"Valid"})).await;
        assert_eq!(status, StatusCode::CREATED, "{signed}");
        let (status, issued) = post_json(state.clone(), format!("/api/v1/app/{app_id}/issue"), json!({"authority_ref":"ISSUE-AC120-001","artifacts":[{"artifact_id":"018f0e2e-6f7a-7b6c-8d5e-4c3b2a190096","media_type":"application/pdf","hash":created["content_hash"]}],"recipients":[{"recipient":{"id":"018f0e2e-6f7a-7b6c-8d5e-4c3b2a190095","kind":"ExternalParty","name":"Supplier","address":"supplier@example.test"},"channel":"Email","acknowledgement_required":true}]})).await;
        assert_eq!(status, StatusCode::CREATED, "{issued}");
        let distribution_id = issued["distribution_ids"][0]
            .as_str()
            .expect("distribution ID");
        let (status, delivered) = post_json(state.clone(), format!("/api/v1/distributions/{distribution_id}/deliver"), json!({"channel_ref":"email-ac120","result":"Delivered","attempted_at":"2026-01-02T00:00:00Z"})).await;
        assert_eq!(status, StatusCode::OK, "{delivered}");
        let (status, acknowledged) = post_json(state, format!("/api/v1/distributions/{distribution_id}/acknowledge"), json!({"recipient_id":"018f0e2e-6f7a-7b6c-8d5e-4c3b2a190095","artifact_hash":created["content_hash"],"method":"SignedReceipt","declaration":"Received","acknowledged_at":"2026-01-03T00:00:00Z"})).await;
        assert_eq!(status, StatusCode::OK, "{acknowledged}");
        assert_eq!(acknowledged["status"], "acknowledged");
    }
}
