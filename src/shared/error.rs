use thiserror::Error;

/// Domain and application rule violations.
///
/// The variants are deliberately stable machine categories. Human-facing
/// messages are carried as text so a later API layer can map them to a
/// localized response without changing domain semantics.
#[derive(Debug, Clone, PartialEq, Eq, Error)]
pub enum DomainError {
    #[error("not found: {0}")]
    NotFound(String),

    #[error("invalid state: {entity} is {actual}; expected {expected}")]
    InvalidState {
        entity: String,
        actual: String,
        expected: String,
    },

    #[error("rule violation: {0}")]
    RuleViolation(String),

    #[error("unauthorized: {0}")]
    Unauthorized(String),

    #[error("immutable record: {0}")]
    Immutable(String),

    #[error("content hash mismatch")]
    ContentHashMismatch,

    #[error("missing prerequisite: {0}")]
    MissingPrerequisite(String),

    #[error("duplicate: {0}")]
    Duplicate(String),
}

impl DomainError {
    pub fn rule(message: impl Into<String>) -> Self {
        Self::RuleViolation(message.into())
    }

    pub fn unauthorized(message: impl Into<String>) -> Self {
        Self::Unauthorized(message.into())
    }

    pub fn missing(message: impl Into<String>) -> Self {
        Self::MissingPrerequisite(message.into())
    }

    pub fn duplicate(message: impl Into<String>) -> Self {
        Self::Duplicate(message.into())
    }

    pub fn immutable(message: impl Into<String>) -> Self {
        Self::Immutable(message.into())
    }

    pub fn not_found(entity: &str, id: impl std::fmt::Display) -> Self {
        Self::NotFound(format!("{entity} {id}"))
    }

    pub fn invalid_state(
        entity: impl Into<String>,
        actual: impl Into<String>,
        expected: impl Into<String>,
    ) -> Self {
        Self::InvalidState {
            entity: entity.into(),
            actual: actual.into(),
            expected: expected.into(),
        }
    }
}
