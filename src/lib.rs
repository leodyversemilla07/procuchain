//! ProcuChain domain core.
//!
//! The first increment implements the governed APP lifecycle slice:
//! PPMP -> Indicative APP -> BAC recommendation -> HoPE approval ->
//! final APP -> signature -> issuance -> delivery -> acknowledgement.
//!
//! Domain decisions live in `domain`; `platform` is an in-memory application
//! service that enforces cross-aggregate prerequisites. Infrastructure
//! adapters (PostgreSQL, object storage, HTTP) are intentionally not part of
//! this increment.

pub mod domain;
pub mod platform;
pub mod shared;
