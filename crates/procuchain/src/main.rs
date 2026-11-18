use procuchain::{ApiState, router_with_state};
use procuchain_postgres::PgStore;
use procuchain_shared::{Actor, ActorRole, TenantId, UserId};
use uuid::Uuid;

#[tokio::main]
async fn main() {
    let address =
        std::env::var("PROCUCHAIN_BIND_ADDRESS").unwrap_or_else(|_| "127.0.0.1:8080".to_owned());
    let listener = tokio::net::TcpListener::bind(&address)
        .await
        .expect("the configured bind address must be available");

    println!("ProcuChain API listening on http://{address}");
    let bearer_token = std::env::var("PROCUCHAIN_DEV_BEARER_TOKEN")
        .unwrap_or_else(|_| "mvp-development-token".to_owned());
    let database_url = std::env::var("DATABASE_URL")
        .expect("DATABASE_URL must be configured for the durable MVP API");
    let store = PgStore::connect(&database_url)
        .await
        .expect("the configured PostgreSQL database must be reachable");
    store
        .migrate()
        .await
        .expect("database migrations must apply");

    let tenant_id = TenantId::from_uuid(parse_configured_uuid(
        "PROCUCHAIN_TENANT_ID",
        "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190099",
    ));
    let actor_id = UserId::from_uuid(parse_configured_uuid(
        "PROCUCHAIN_ACTOR_ID",
        "018f0e2e-6f7a-7b6c-8d5e-4c3b2a190098",
    ));

    let state = ApiState::with_bearer_token(
        store,
        tenant_id,
        Actor::new(actor_id, ActorRole::BacChairperson, "MVP BAC Chairperson"),
        bearer_token,
    );
    axum::serve(listener, router_with_state(state))
        .await
        .expect("API server failed");
}

fn parse_configured_uuid(name: &str, default: &str) -> Uuid {
    let value = std::env::var(name).unwrap_or_else(|_| default.to_owned());
    Uuid::parse_str(&value).unwrap_or_else(|error| panic!("{name} must be a UUID: {error}"))
}
